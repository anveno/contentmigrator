<?php
$oMigrator = new contentmigrator;

$addon = rex_addon::get("contentmigrator");
if (file_exists($addon->getAssetsPath("css/style.css"))) {
    rex_view::addCssFile($addon->getAssetsUrl("css/style.css"));
}
if (file_exists($this->getAssetsPath("js/script.js"))) {
    rex_view::addJSFile($this->getAssetsUrl('js/script.js'));
}