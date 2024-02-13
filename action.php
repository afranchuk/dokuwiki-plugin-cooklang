<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Form\InputElement;

class action_plugin_cooklang extends ActionPlugin {
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('EDIT_FORM_ADDTEXTAREA', 'BEFORE', $this, '_editform');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_handle_edit_post');
    }

    public function _editform(Doku_Event $event) {
        global $TEXT;
        global $RANGE;
        if ($event->data['target'] !== 'plugin_cooklang') {
            return;
        }
        $event->preventDefault();
 
        unset($event->data['intro_locale']);
 
        $event->data['media_manager'] = false;
 
        preg_match('|^<cooklang(.*?)>(.*?)</cooklang>$|s', $TEXT, $matches);
        $attr_string = $matches[1];
        $content = trim($matches[2]);

        $attrs = array();
        foreach (explode(' ', trim($attr_string)) as $part) {
            list($name,$value) = explode('=', $part, 2);
            $attrs[$name] = $value;
        }

        $servings = $attrs['servings'];
        if (!$servings) {
            preg_match('/^>> servings: (.*)$/m', $content, $matches);
            $servings = $matches[1] ?? '1';
        }
 
        $event->data['form']->addElement(new InputElement('number', 'servings', 'Displayed Servings'))->attr('min', '1')->val($servings);
        $event->data['form']->addTag('br');
        $event->data['form']->addTextarea('recipe', '')->val($content)->attr('cols', '80')->attr('rows', '10');
    }

    public function _handle_edit_post(Doku_Event $event) {
        if (!isset($_POST['servings']) || !isset($_POST['recipe'])) {
            return;
        }

        global $TEXT;
        $TEXT = "<cooklang servings=" . $_POST['servings'] . ">\n" . $_POST['recipe'] . "\n</cooklang>";
    }
}
