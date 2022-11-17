<?php
$oMigrator = new contentmigrator;
$csrfToken = rex_csrf_token::factory('contentmigrator');

if (rex_post('import', 'string')) {

    if (!$csrfToken->isValid()) {
        echo rex_view::error("Ein Fehler ist aufgetreten. Bitte wenden Sie sich an den Webmaster.");
        return;
    }

    $aFileContent = json_decode(file_get_contents($_FILES["importfile"]["tmp_name"]), 1);
    $iArticlesId = rex_request('nv_articles_id', 'int');
    $oMigrator->import($iArticlesId, $aFileContent);
    echo rex_view::success($this->i18n('contentmigrator_imported'));

    //dump($aFileContent);

    if (count($aFileContent["media"]) > 0) {
        $sContent = '<div class="container-fluid">';
        $sContent .= '<div class="row">';
        $sContent .= '<div class="col-lg-3"><strong>'.$this->i18n('contentmigrator_label_filename').'</strong></div>';
        $sContent .= '<div class="col-lg-3"><strong>'.$this->i18n('contentmigrator_label_category').'</strong></div>';
        $sContent .= '<div class="col-lg-2"><strong>'.$this->i18n('contentmigrator_label_width').'</strong></div>';
        $sContent .= '<div class="col-lg-2"><strong>'.$this->i18n('contentmigrator_label_height').'</strong></div>';
        $sContent .= '<div class="col-lg-2"><strong>'.$this->i18n('contentmigrator_label_exists').'</strong></div>';

        $sContent .= '</div>';
        foreach ($aFileContent["media"] as $aMedia) {
            $sMediaExists = '<span class="text-danger">'.$this->i18n('contentmigrator_no').'</span>';
            $sClass = 'text-danger';
            $oExistingMedia = $oMigrator->checkMediaExists($aMedia["filename"],$aMedia["width"],$aMedia["height"],$aMedia["filesize"]);
            if ($oExistingMedia->getRows()) {
                $sClass = '';
                $sMediaExists = '<a href="'.rex::getServer().'media/'.$oExistingMedia->getValue("filename").'" target="_blank">'.$oExistingMedia->getValue("filename").'</a>';
            }

            $sContent .= '<div class="row">';
            $sContent .= '<div class="col-lg-3 js-imageToImport" data-imagename="' . $aFileContent["articles"][0][1]["articlecontent"]["server"] . 'media/' . $aMedia["filename"] . '"><a href="' . $aFileContent["articles"][0][1]["articlecontent"]["server"] . 'media/' . $aMedia["filename"] . '" target="_blank" class="'.$sClass.'">' . $aMedia['filename'].'</a></div>';
            $sContent .= '<div class="col-lg-3">'.$aMedia["path"].'</div>';
            $sContent .= '<div class="col-lg-2">'.$aMedia["width"].'px</div>';
            $sContent .= '<div class="col-lg-2">'.$aMedia["height"].'px</div>';
            $sContent .= '<div class="col-lg-2">'.$sMediaExists.'</div>';
            $sContent .= '</div>';
        }
        $sContent .= '<div class="row" style="padding-top: 20px; padding-bottom: 15px">';
        $sContent .= '<div class="col-lg-3">';
        $sContent .= '<input type="number" id="targetCat" placeholder="Ziel-Medienpool-Kategorie" />';
        $sContent .= '</div>';
        $sContent .= '<div class="col-lg-6">';
        $sContent .= '<a class="btn btn-save js-startImport" href="#">Bilder-Import starten</a>';
        $sContent .= '</div>';
        $sContent .= '</div>';

        $sContent .= '<div class="row">';
        $sContent .= '<div id="fr-importContainer">';
        $sContent .= '<div class="col-lg-12">';
        $sContent .= '<!-- Import-Status -->';
        $sContent .= '</div>';
        $sContent .= '</div>';
        $sContent .= '</div>';
        $sContent .= '</div>';


        $fragment = new rex_fragment();
        $fragment->setVar("class", "edit");
        $fragment->setVar('title', $this->i18n('contentmigrator_title_used_media'), false);
        $fragment->setVar('body', $sContent, false);
        $output = $fragment->parse('core/page/section.php');
        
        echo $output;
    }


    return;
}

echo rex_view::warning($this->i18n('contentmigrator_warning_content_deleted'));

$aTree = $oMigrator->getTree();
$sContent = '<div class="container-fluid">';
$sContent .= $oMigrator->parseTreeList($aTree);
$sContent .= $oMigrator->getUploadField();
$sContent .= '</div>';

$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save" type="submit" name="import" value="1">' . $this->i18n('contentmigrator_btn_import') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');
$buttons = '
<fieldset class="rex-form-action">
' . $buttons . '
</fieldset>
';

$fragment = new rex_fragment();
$fragment->setVar("class", "edit");
$fragment->setVar('title', $this->i18n('contentmigrator_title_import'), false);
$fragment->setVar('body', $sContent, false);
$fragment->setVar("buttons", $buttons, false);
$output = $fragment->parse('core/page/section.php');

$output = '<form action="' . rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">'
    . $csrfToken->getHiddenField()
    . $output
    . '</form>';

echo $output;
