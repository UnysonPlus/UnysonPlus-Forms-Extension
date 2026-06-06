<?php if (!defined('FW')) die('Forbidden');

$manifest = array();

$manifest['name']        = __( 'Forms', 'fw' );
$manifest['slug']        = 'unysonplus-forms';
$manifest['description'] = __(
	'This extension adds the possibility to create a contact form. Use the drag & drop form builder to create any contact form you\'ll ever want or need.',
	'fw'
);

$manifest['version']     = '2.0.34';
$manifest['display']     = false;
$manifest['standalone']  = false;

// Repository Info
$manifest['github_update'] = 'UnysonPlus/UnysonPlus-Forms-Extension';
$manifest['github_repo']   = 'https://github.com/UnysonPlus/UnysonPlus-Forms-Extension';
$manifest['github_branch'] = 'master';

// Author Info
$manifest['author']     = 'UnysonPlus';
$manifest['author_uri'] = 'https://www.lastimosa.com.ph/unysonplus';

// Requirements
$manifest['requirements'] = array(
	'extensions' => array(
		'builder' => array(),
	),
);

// Meta
$manifest['license']      = 'GPL-2.0-or-later';
$manifest['text_domain']  = 'fw';
$manifest['requires_php'] = '7.4';
$manifest['requires_wp']  = '5.8';
