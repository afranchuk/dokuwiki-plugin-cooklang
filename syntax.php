<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * DokuWiki Plugin cooklang (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Alex Franchuk <alex.franchuk@gmail.com>
 */
class syntax_plugin_cooklang extends SyntaxPlugin
{
    /** @inheritDoc */
    public function getType()
    {
        return 'substition';
    }

    /** @inheritDoc */
    public function getPType()
    {
        return 'block';
    }

    /** @inheritDoc */
    public function getSort()
    {
        return 200;
    }

    /** @inheritDoc */
    public function connectTo($mode)
    {
        $this->Lexer->addEntryPattern('<cooklang\b(?=.*?>.*?</cooklang>)', $mode, 'plugin_cooklang');
    }

    /** @inheritDoc */
    public function postConnect()
    {
        $this->Lexer->addExitPattern('</cooklang>', 'plugin_cooklang');
    }

    /** @inheritDoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        if ($state !== DOKU_LEXER_UNMATCHED) {
            return array();
        }

        // Parse attributes (it's a bit easier to do it this way to keep this class stateless.
        list($attr_string,$content) = explode('>', $match, 2);

        // Very basic attribute parsing for space-separated `name=value` pairs.
        $attrs = array();
        foreach (explode(' ', trim($attr_string)) as $part) {
            list($name,$value) = explode('=', $part, 2);
            $attrs[$name] = $value;
        }

        $process = proc_open(array('/usr/local/bin/cook', 'recipe', 'read', '--format', 'json'), array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        ), $pipes);

        if (is_resource($process)) {
            fwrite($pipes[0], $content);
            fclose($pipes[0]);

            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $result = proc_close($process);
            if ($result == 0) {
                $json = json_decode($output, true);

                return array($json, $attrs, array($pos - 9, $pos + strlen($match) + 11));
            } else {
                return array(sprintf("'cook' returned %d: %s", $result, $err));
            }
        }

        return array("'cook' program missing");
    }

    private function number_to_string($multiplier, $number)
    {
        switch ($number['type']) {
        case 'regular':
            return (string)($number['value'] * $multiplier);
        case 'fraction':
            $whole = $number['value']['whole'] ? (string)($number['value']['whole'] * $multiplier) . " " : "";
            return $whole . (string)($number['value']['num'] * $multiplier) . "/" . (string)$number['value']['den'];
        default:
            return "(unknown number)";
        }
    }

    /** @inheritDoc */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode === 'xhtml' && $data) {
            list($json, $attrs, $dsp) = $data;

            if (!$dsp) {
                $renderer->doc .= $json;
                return true;
            }

            $servings = $json['metadata']['servings'];
            $display_servings = $attrs['servings'];
            $multiplier = $display_servings && $servings[0] ? (float)$display_servings / (float)$servings[0] : 1;

            // Start edit section
            $class = $renderer->startSectionEdit($dsp[0], ['target' => 'plugin_cooklang', 'name' => 'recipe']);
            $renderer->doc .= '<div class="' . $class . '">';

            $renderer->doc .= sprintf("<p><b>servings: %d</b></p>", $servings[0] * $multiplier);

            $sections = $json['sections'];
            $ingredients = $json['ingredients'];
            $cookware = $json['cookware'];
            $timers = $json['timers'];

            $renderer->doc .= "<h3>Ingredients</h3>";

            $renderer->doc .= "<ul>";
            foreach ($ingredients as &$ingredient) {
                $quantity = $ingredient['quantity'];
                $renderer->doc .= "<li>";
                if ($quantity) {
                    $renderer->doc .= $this->number_to_string($multiplier, $quantity['value']['value']) . " ";
                    if ($quantity['unit']) {
                        $renderer->doc .= $renderer->_xmlEntities($quantity['unit']) . " ";
                    }
                }
                $renderer->doc .= $renderer->_xmlEntities($ingredient['name']);
                $renderer->doc .= "</li>";
            }
            $renderer->doc .= "</ul>";

            $first_section = true;
            foreach ($sections as &$section) {
                if (!$first_section) {
                    $renderer->doc .= "<hr>";
                }

                $renderer->doc .= "<h3>Instructions</h3>";

                $steps = $section['steps'];
                $renderer->doc .= "<ol>";
                foreach ($steps as &$step) {
                    $renderer->doc .= "<li>";
                    $items = $step['items'];
                    foreach ($items as &$item) {
                        switch ($item['type']) {
                        case 'text':
                            $renderer->doc .= $renderer->_xmlEntities($item['value']);
                            break;
                        case 'ingredient':
                            $renderer->doc .= $renderer->_xmlEntities($ingredients[$item['index']]['name']);
                            break;
                        case 'cookware':
                            $renderer->doc .= $renderer->_xmlEntities($cookware[$item['index']]['name']);
                            break;
                        case 'timer':
                            $timer = $timers[$item['index']]['quantity'];
                            $renderer->doc .= $this->number_to_string(1, $timer['value']['value']) . " " . $renderer->_xmlEntities($timer['unit']);
                            break;
                        }
                    }
                    $renderer->doc .= "</li>";
                }
                $renderer->doc .= "</ol>";


                $first_section = false;
            }

            // End edit section
            $renderer->doc .= "</div>";
            $renderer->finishSectionEdit($dsp[1]);

            // $renderer->doc .= "<pre>" . var_export($json, true) . "</pre>";

            return true;
        }

        return false;
    }
}
