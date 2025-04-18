<?php
class Page {
    private $template;

    public function __construct($template) {
        $this->template = file_get_contents($template);
    }

    public function Render($data) {
        $output = $this->template;
        foreach ($data as $key => $value) {
            $output = str_replace("{{ $key }}", $value, $output);
        }
        return $output;
    }
}
