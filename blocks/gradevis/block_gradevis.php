<?php

class block_gradevis extends block_base {

    public function init() {
        $this->title = get_string('gradevis', 'block_gradevis');
    }

    public function instance_allow_multiple() {
        return true;
    }

    function has_config() {
        return true;
    }

    public function hide_header() {
        return false;
    }

    public function applicable_formats() {
        return array('course-view' => true);
    }

    public function specialization() {
        if (isset($this->config)) {
            if (empty($this->config->title)) {
                $this->title = get_string('defaulttitle', 'block_gradevis');
            } else {
                $this->title = $this->config->title;
            }

            if (empty($this->config->text)) {
                $this->config->text = get_string('defaulttext', 'block_gradevis');
            }
        }

        // May also be able to include the require AMD call here? See block comments.
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        if (!empty($this->config->text)) {
            $this->content->text = $this->config->text;
        }

        // Get the content from the block renderer.
        $renderer = $this->page->get_renderer('block_gradevis');
        $block = new \block_gradevis\output\block();

        $this->content->text = $renderer->render_block($block);
        $this->content->footer = 'Footer here...';

        // Setting no content here will hide the block. Useful perhaps.
        //$this->content->text = '';
        //$this->content->footer = '';

        return $this->content;
    }

    public function instance_config_save($data, $nolongerused = false) {
        if (get_config('gradevis', 'Allow_HTML') != '1') {
            $data->text = strip_tags($data->text);
        }

        // And now forward to the default implementation defined in the parent class
        return parent::instance_config_save($data, $nolongerused);
    }
}