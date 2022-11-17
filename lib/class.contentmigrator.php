<?php class contentmigrator
{

    protected $aMediaUsedTotal;

    public function __construct()
    {
        $this->addon = rex_addon::get('contentmigrator');
        $this->aMediaUsedTotal = array();
    }

    /*
     * STRUCTURE TREE
     */

    public function getTree($iParentId = 0, $iLevel = 0)
    {

        $aItems = array();
        $oItems = rex_sql::factory();
        $sQuery = "SELECT catname,id,parent_id,priority,catpriority 
                    FROM " . rex::getTablePrefix() . "article 
                    WHERE parent_id = '$iParentId' && startarticle = '1' 
                            && clang_id = '" . rex_clang::getCurrentId() . "'  
                    ORDER BY catpriority ASC";
        $oItems->setQuery($sQuery);

        foreach ($oItems as $oItem) {
            array_push($aItems,
                        array(
                            'name' => $oItem->getValue('catname'),
                            'level' => $iLevel,
                            'priority' => $oItem->getValue('catpriority'),
                            'id' => $oItem->getValue('id'),
                            'parent_id' => $oItem->getValue('parent_id'),
                            'children' => $this->getTree($oItem->getValue('id'), $iLevel + 1)
                        )
            );
        }

        return $aItems;
    }

    public function parseTreeList($aItems)
    {
        // Root-Kategorie ergänzen
        array_unshift($aItems,
            array(
                'name' => 'Hauptebene',
                'level' => 0,
                'priority' => 1,
                'id' => 0,
                'parent_id' => '',
                'children' => []
            )
        );

        $aOut = array();

        $aOut[] = '<div class="row">';
        $aOut[] = '<div class="col-12">
                        <strong>' . $this->addon->i18n('contentmigrator_label_choose_article') . '</strong><br>
                        <select class="form-control selectpicker" data-live-search="true" name="nv_articles_id">' . $this->parseTreeSelection("nv_articles_id", $aItems) . '</select>
                  </div>';
        $aOut[] = '</div><br>';

        $sOut = implode("\n", $aOut);
        return $sOut;
    }

    public function getUploadField()
    {
        $aOut = array();
        $aOut = array();

        $aOut[] = '<div class="row">';
        $aOut[] = '<div class="col-12"><strong>' . $this->addon->i18n('contentmigrator_label_jsonfile') . '</strong><br><input class="form-control" type="file" accept=".json" name="importfile" required="required"></div>';
        $aOut[] = '</div><br>';

        $sOut = implode("\n", $aOut);
        return $sOut;
    }

    public function parseTreeSelection($sFieldname, $aItems)
    {
        $aOut = array();
        $sCheckValue = rex_request($sFieldname, 'int');
        foreach ($aItems as $aItem) {
            $aOut[] = '<option value="' . $aItem["id"] . '" ';
            if ($sCheckValue == $aItem["id"]) {
                $aOut[] = 'selected';
            }
            $aOut[] = '>';
            for ($x = 0; $x < $aItem["level"]; $x++) {
                $aOut[] = '&nbsp;&nbsp;';
            }

            $aOut[] = $aItem["name"] . ' (ID: ' . $aItem["id"] . ')</option>';
            if (count($aItem["children"])) {
                $aOut[] = $this->parseTreeSelection($sFieldname, $aItem["children"]);
            }
        }
        $sOut = implode("\n", $aOut);
        return $sOut;
    }

    /*
     * EXPORT
     */

    public function export($iArticlesId)
    {
        $iArticlesId = (int) $iArticlesId;
        $oArticle = rex_article::get($iArticlesId);
        if (!$oArticle->getValue("id")) return;

        $aArr = $this->exportCategory($iArticlesId);
        $aArr['media'] = $this->aMediaUsedTotal;

        //dump($aArr);
        //return;

        $sFileContent = json_encode($aArr);

        $sFilename = 'contentmigrator_export_article_' . $oArticle->getName() . '_' . $oArticle->getValue('id') . '_' . date('YmdHis') . '.json';
        header('Content-Disposition: attachment; filename="' . $sFilename . '"; charset=utf-8');
        rex_response::sendContent($sFileContent, 'application/octetstream');
        exit;
    }

    public function exportCategory($iArticlesId) {
        $aCat = array();
        $cat = rex_category::get($iArticlesId);
        if ($cat) {
            $articles = $cat->getArticles(false);
            foreach ($articles as $art) {
                $aCat['articles'][] = $this->exportArticleContent($art->getId());
            }
            $child_categories = rex_category::get($iArticlesId)->getChildren(false);
            foreach ($child_categories as $child_category) {
                $aCat['childs'][] = $this->exportCategory($child_category->getId());
            }
        }

        $aMediaUsed = $this->getUsedMedia($iArticlesId);
        foreach ($aMediaUsed as $aMedia) {
            if (!in_array($aMedia["filename"], array_column($this->aMediaUsedTotal, 'filename'))) {
                array_push($this->aMediaUsedTotal, $aMedia);
            }
        }

        return $aCat;
    }

    public function exportArticleContent($iArticlesId) {
        $oArticle = rex_article::get($iArticlesId);

        $gc = \rex_sql::factory();
        $gc->setQuery(
            'SELECT * FROM '.\rex::getTable('article').' WHERE `id` = :from_id',
            ['from_id' => $iArticlesId]
        );

        if ($gc->getRows() > 0) {

            $articles = array();

            $cols = \rex_sql::factory();
            $cols->setQuery('SHOW COLUMNS FROM '.\rex::getTablePrefix().'article');

            foreach ($gc as $article) {
                $articleMetas = array();
                foreach ($cols as $col) {
                    $colname = $col->getValue('Field');
                    $value = $article->getValue($colname);

                    if ($colname != 'id' && $colname != 'pid' && $colname != 'parent_id') {
                        $articleMetas[$colname] = $value;
                    }
                }
                $articleMetas['server'] = rex::getServer();
                $articleMetas['content'] = $this->exportSlices($iArticlesId, $article->getValue('clang_id'));

                $articles[$article->getValue('clang_id')]['articlecontent'] = $articleMetas;
            }

            return $articles;
        }

    }

    public function exportSlices($iArticlesId, $from_clang) {

        $gc = \rex_sql::factory();
        $gc->setQuery(
            'SELECT * FROM '.\rex::getTable('article_slice').' WHERE `article_id` = :from_id AND `clang_id` = :from_clang',
            ['from_id' => $iArticlesId, 'from_clang' => $from_clang]
        );

        if ($gc->getRows() > 0) {

            $slices = array();

            $cols = \rex_sql::factory();
            $cols->setQuery('SHOW COLUMNS FROM '.\rex::getTablePrefix().'article_slice');

            foreach ($gc as $slice) {
                $sliceContent = array();
                foreach ($cols as $col) {
                    $colname = $col->getValue('Field');
                    /*if ($colname == 'clang_id') {
                        $value = $to_clang;
                    } elseif ($colname == 'article_id') {
                        $value = $to_id;
                    }*/
                    $value = $slice->getValue($colname);
                    if ($colname != 'id') {
                        $sliceContent[$colname] = $value;
                    }
                }
                array_push($slices, $sliceContent);
            }

            return $slices;
        }

    }

    /*
     * IMPORT
     */

    public function import(int $iArticlesId, $aFileContent = [])
    {
        $oArticle = rex_article::get($iArticlesId);
        if (!$oArticle->getValue("id")) return;

        $this->importChilds($aFileContent, $iArticlesId);
    }

    public function importChilds($aContent, $category_id) {
        $new_category_id = $this->importArticles($aContent['articles'], $category_id);
        //dump($new_category_id);
        if (array_key_exists('childs', $aContent)) {
            foreach ($aContent['childs'] as $child) {
                $this->importChilds($child, $new_category_id);
            }
        }
    }

    public function importArticles($articles, $category_id) {

        $new_category_id = $category_id;

        foreach ($articles as $clang) {
            unset($id);
            foreach ($clang as $clang_key => $articleContent) {

                $data = $articleContent['articlecontent'];
                $data['category_id'] = $new_category_id;
                //dump($data);

                /*
                // Neuen Artikel mit data erstellen:
                rex_article_service::addArticle($data);

                // ID des hinzugefügten Artikels herausfinden:
                $lastIdSql = \rex_sql::factory();
                $lastIdSql->setQuery('SELECT LAST_INSERT_ID() FROM ' . \rex::getTablePrefix() . 'article');
                $lastId = $lastIdSql->getValue('.LAST_INSERT_ID()');
                */

                // ++++++++++++++++++++++++++++++++++++++++++++++++++

                // Alternative zu rex_article_service::addArticle
                // siehe addArticle in service_article.php

                // parent may be null, when adding in the root cat
                $parent = rex_category::get($data['category_id']);
                if ($parent) {
                    $path = $parent->getPath();
                    $path .= $parent->getId() . '|';
                } else {
                    $path = '|';
                }

                $AART = rex_sql::factory();

                $AART->setTable(rex::getTablePrefix() . 'article');
                if (!isset($id) || !$id) {
                    $id = $AART->setNewId('id');
                } else {
                    $AART->setValue('id', $id);
                }
                $AART->setValue('clang_id', $clang_key);
                $AART->setValue('path', $path);
                $AART->setValue('parent_id', $data['category_id']);

                $dontCopy = array('id',
                                'pid',
                                'parent_id',
                                'path',
                                'clang_id',
                                'category_id',
                                'content',
                                'server',
                                'seocu_data');

                foreach (array_diff(array_keys($data), $dontCopy) as $fldName) {
                    $AART->setValue($fldName, $data[$fldName]);
                }

                try {
                    $AART->insert();
                    // ----- PRIOR
                    //self::newArtPrio($data['category_id'], $clang_key, 0, $data['priority']);
                } catch (rex_sql_exception $e) {
                    throw new rex_api_exception($e->getMessage().' - '.$id, $e);
                }

                // ++++++++++++++++++++++++++++++++++++++++++++++++++

                // Slices importieren:
                if (!is_null($data["content"])) {
                    foreach ($data["content"] as $aSlice) {
                        unset($aSlice['article_id']);
                        $oRes = rex_content_service::addSlice($id, $aSlice["clang_id"], $aSlice["ctype_id"], $aSlice["module_id"], $aSlice);
                    }
                }

                // ++++++++++++++++++++++++++++++++++++++++++++++++++

                rex_article_cache::delete($id, $clang_key);
            }

            // neue category_id für folgende Artikel festlegen:
            if ($data['startarticle'] === 1) {
                $new_category_id = $id;
            }

        }
        return $new_category_id;
    }

    /*
     * MEDIA
     */

    public function getUsedMedia($iArticlesId)
    {
        $iArticlesId = (int) $iArticlesId;
        $oArticle = rex_article::get($iArticlesId);
        if (!$oArticle->getValue("id")) return;

        $aFiles = [];
        $sQuery = 'SELECT * FROM ' . rex::getTablePrefix() . 'media';

        $oDbQ = rex_sql::factory();
        $aItems = $oDbQ->getArray($sQuery);
        if (count($aItems)) {
            foreach ($aItems as $aItem) {
                $sFilename = $aItem['filename'];
                if ($this->checkMediaUsed($sFilename, $iArticlesId)) {
                    $aMediaPath = [];
                    if ($aItem["category_id"]) {
                        $aMediaPath = rex_media_category::get($aItem["category_id"])->getPathAsArray();
                        $aMediaPath[] = $aItem["category_id"];
                    }
                    $aMediaPathLabel = [];
                    foreach ($aMediaPath as $iCategoryId) {
                        $aMediaPathLabel[] = rex_media_category::get($iCategoryId)->getName();
                    }
                    $sMediaPath = implode(" > ", $aMediaPathLabel);

                    $aFiles[] = array(
                        "id" => $aItem["id"],
                        "filename" => $sFilename,
                        "originalname" => $aItem["originalname"],
                        "category_id" => $aItem["category_id"],
                        "path" => $sMediaPath,
                        "filesize" => $aItem["filesize"],
                        "width" => $aItem["width"],
                        "height" => $aItem["height"]
                    );
                }
            }
        }
        return $aFiles;
    }

    public function checkMediaUsed($sFilename, $iArticlesId)
    {
        $iArticlesId = (int) $iArticlesId;
        $oArticle = rex_article::get($iArticlesId);
        if (!$oArticle->getValue("id")) return;

        $oDbQ = rex_sql::factory();
        // FIXME move structure stuff into structure addon
        $values = [];
        for ($i = 1; $i < 21; ++$i) {
            $values[] = 'value' . $i . ' REGEXP ' . $oDbQ->escape('(^|[^[:alnum:]+_-])' . $sFilename);
        }

        $files = [];
        $filelists = [];
        $escapedFilename = $oDbQ->escape($sFilename);
        for ($i = 1; $i < 11; ++$i) {
            $files[] = 'media' . $i . ' = ' . $escapedFilename;
            $filelists[] = 'FIND_IN_SET(' . $escapedFilename . ', medialist' . $i . ')';
        }

        $where = '';
        $where .= implode(' OR ', $files) . ' OR ';
        $where .= implode(' OR ', $filelists) . ' OR ';
        $where .= implode(' OR ', $values);

        $from = '';
        if ($iArticlesId > 0) {
            $from = 'LEFT JOIN ' . rex::getTablePrefix() . 'article AS a ON s.article_id = a.id ';
            $where = '(a.id = "' . $iArticlesId . '" OR a.path LIKE "|' . $iArticlesId . '|%") AND (' . $where . ')';
        }

        $sQuery = 'SELECT DISTINCT s.article_id, s.clang_id FROM ' . rex::getTablePrefix() . 'article_slice AS s ' . $from . ' WHERE ' . $where;

        $oDbQ->getArray($sQuery);
        if ($oDbQ->getRows() > 0) {
            return true;
        }

        return false;
    }

    public function checkMediaExists($sFilename, $iWidth, $iHeight, $iFilesize)
    {
        $oDb = rex_sql::factory();
        $sQuery = 'SELECT * FROM ' . rex::getTablePrefix() . 'media WHERE filename = :filename && width = :width && height = :height && filesize = :filesize Limit 1';
        $oDb->setQuery($sQuery, ['filename' => $sFilename, 'width' => $iWidth, 'height' => $iHeight, 'filesize' => $iFilesize]);
        return $oDb;
    }
}
