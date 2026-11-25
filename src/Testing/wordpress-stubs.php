<?php

use MM\Meros\Foundations\Testing\WordPressState;

function add_filter($tag, $callback, $priority = 10, $acceptedArgs = 1)
{
    WordPressState::$hooks[$tag][$priority][] = [$callback, $acceptedArgs];
    return true;
}

function add_action($tag, $callback, $priority = 10, $acceptedArgs = 1)
{
    return add_filter($tag, $callback, $priority, $acceptedArgs);
}

function apply_filters($tag, $value, ...$args)
{
    $priorities = WordPressState::$hooks[$tag] ?? [];
    ksort($priorities);
    foreach ($priorities as $callbacks) {
        foreach ($callbacks as [$callback, $acceptedArgs]) {
            $value = $callback(...array_slice([$value, ...$args], 0, $acceptedArgs));
        }
    }
    return $value;
}

function do_action($tag, ...$args)
{
    $priorities = WordPressState::$hooks[$tag] ?? [];
    ksort($priorities);
    foreach ($priorities as $callbacks) {
        foreach ($callbacks as [$callback, $acceptedArgs]) {
            $callback(...array_slice($args, 0, $acceptedArgs));
        }
    }
}

function get_option($name, $default = false) { return WordPressState::$options[$name] ?? $default; }
function update_option($name, $value) { WordPressState::$options[$name] = $value; return true; }
function get_bloginfo($key) { return 'Meros Tests'; }
function home_url($path = '') { return 'https://meros.test' . $path; }
function get_locale() { return 'en_GB'; }
function get_template_directory() { return WordPressState::$theme; }
function get_stylesheet_directory() { return WordPressState::$theme; }
function get_theme_file_path($path = '') { return WordPressState::$theme . '/' . ltrim($path, '/'); }
function get_parent_theme_file_path($path = '') { return get_theme_file_path($path); }
function wp_upload_dir() { return ['basedir' => get_theme_file_path('uploads'), 'baseurl' => home_url('/uploads')]; }
function is_ssl() { return true; }
