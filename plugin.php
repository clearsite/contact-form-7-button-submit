<?php
/*
	Plugin Name: Contact Form 7 - Button-submit
	Plugin URI: $URL$
	Description: Tweaks to CF7 to make it better HTML5 compliant
	Author: Remon Pel
	Version: 0.0.1
	Author URI: http://remonpel.nl/
	License: GPL v2 or later
*/

// do our thing JUST after the CF7 magic happens.
// This happens at 55, so we tweak at 56...
// this is taken from   contact-form-7/modules/submit.php

add_action('wpcf7_init', 'cls_cf7_submit_button', 56);

function cls_cf7_submit_button()
{
    // fetch the function definition
    $fr = new ReflectionFunction('wpcf7_submit_shortcode_handler');
    // if we can't find the function definition, then we have either an ancient or a very-new-very-different version of CF7
    if ($fr) {
        // find out the file
        $file = $fr->getFileName();
        // grab the position within the file
        $startline = $fr->getStartLine();
        $endline = $fr->getEndLine();
        // get the function parameters
        $parameters = $fr->getParameters();

        // get file content
        $function_definition = file($file);
        // and keep the part the function is defined in
        $function_definition = array_slice($function_definition, $startline - 1, $endline - $startline + 1);
        // strip the function definition line and the function body closing tag
        $function_body = array_slice($function_definition, 1, count($function_definition) - 2);

        // different versions have different methods
        $tweaks = array(
            '2.4' => array(
                '<input type="submit"',
                '<input type="submit" value="\' . esc_attr( $value ) . \'"\' . $atts . \' />' => '<button \'. $atts . \'>\' . $value ) . \'</button>'
            ),
            '3.4' => array(
                '<input %1$s />',
                '<input %1$s />' => '<button %1$s>',
                ');' => ') . $value . \'</button>\';'
            )
        );

        $found = false;
        $tweak = array();
        krsort($tweaks);

        // find the correct tweak.
        foreach ($tweaks as $version => $tweak) {
            if (version_compare(WPCF7_VERSION, $version, '>=')) {
                $found = true;
                break;
            }
        }
        if (!$found)
            $tweak = array();
        $detector = array_shift($tweak);

        // change element; loop through the lines of code, find the line matching $detector and change the line with
        // the replacements in $tweak
        foreach ($function_body as $i => $line) {
            if (false !== strpos($line, $detector)) {
                $function_body[$i] = strtr($line, $tweak);
            }
        }

        // make the body a String again
        $function_body = implode("\n", $function_body);

        // make a nice paramters list, like   $a,$b
        $p = ''; // this method is awkward, but CPU-wise cheaper than an array_walk + implode
        foreach ($parameters as $parameter) $p .= ",$" . $parameter->getName();
        $parameters = trim($p, ',');

        // create the function
        $function = create_function($parameters, $function_body);

        // overwrite the submit shortcode
        wpcf7_add_shortcode('submit', $function);
    }
}

