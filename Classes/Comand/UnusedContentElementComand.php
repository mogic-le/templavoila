<?php

/*
 * This file is part of the TemplaVoilà project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace Schnitzler\Templavoila\Comand;

use Schnitzler\Templavoila\Service\ApiService;
use Schnitzler\Templavoila\Traits\DatabaseConnection;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Lowlevel\CleanerCommand;

/**
 * Cleaner module: Finding unused content elements on pages.
 * User function called from tx_lowlevel_cleaner_core configured in ext_localconf.php
 * See system extension, lowlevel!
 *
 *
 */
class UnusedContentElementComand extends CleanerCommand
{
    use DatabaseConnection;

    /**
     * @var array
     */
    protected $resultArray;

    /**
     * @var bool
     */
    public $checkRefIndex = true;

    /**
     * @var array
     */
    protected $excludePageIdList = [];

    public function __construct()
    {
        parent::__construct();

        $this->genTree_traverseDeleted = false;
        $this->genTree_traverseVersions = false;

        // Setting up help:
        $this->cli_options[] = ['--echotree level', 'When "level" is set to 1 or higher you will see the page of the page tree outputted as it is traversed. A value of 2 for "level" will show even more information.'];
        $this->cli_options[] = ['--pid id', 'Setting start page in page tree. Default is the page tree root, 0 (zero)'];
        $this->cli_options[] = ['--depth int', 'Setting traversal depth. 0 (zero) will only analyse start page (see --pid), 1 will traverse one level of subpages etc.'];
        $this->cli_options[] = ['--excludePageIdList commalist', 'Specifies page ids to exclude from the processing.'];

        $this->cli_help['name'] = 'tx_templavoila_unusedce -- Find unused content elements on pages';
        $this->cli_help['description'] = trim('
Traversing page tree and finding content elements which are not used on pages and seems to have no references to them - hence is probably "lost" and could be deleted.

Automatic Repair:
- Silently deleting the content elements
- Run repair multiple times until no more unused elements remain.
');

        $this->cli_help['examples'] = '';
    }

    /**
     * Main function
     *
     * @return array
     */
    public function main()
    {
        $resultArray = [
            'message' => $this->cli_help['name'] . chr(10) . chr(10) . $this->cli_help['description'],
            'headers' => [
                'all_unused' => ['List of all unused content elements', 'All elements means elements which are not used on that specific page. However, they could be referenced from another record. That is indicated by index "1" which is the number of references leading to the element.', 1],
                'deleteMe' => ['List of elements that can be deleted', 'This is all elements which had no references to them and hence should be OK to delete right away.', 2],
            ],
            'all_unused' => [],
            'deleteMe' => [],
        ];

        $startingPoint = $this->cli_isArg('--pid') ? MathUtility::forceIntegerInRange((int)$this->cli_argValue('--pid'), 0) : 0;
        $depth = $this->cli_isArg('--depth') ? MathUtility::forceIntegerInRange((int)$this->cli_argValue('--depth'), 0) : 1000;
        $this->excludePageIdList = $this->cli_isArg('--excludePageIdList') ? GeneralUtility::intExplode(',', (string)$this->cli_argValue('--excludePageIdList')) : [];

        $this->resultArray = & $resultArray;
        $this->genTree($startingPoint, $depth, (int) $this->cli_argValue('--echotree'), 'main_parseTreeCallBack');

        ksort($resultArray['all_unused']);
        ksort($resultArray['deleteMe']);

        return $resultArray;
    }

    /**
     * Call back function for page tree traversal!
     *
     * @param string $tableName Table name
     * @param int $uid UID of record in processing
     * @param int $echoLevel Echo level  (see calling function
     * @param string $versionSwapmode Version swap mode on that level (see calling function
     * @param int $rootIsVersion Is root version (see calling function
     */
    public function main_parseTreeCallBack($tableName, $uid, $echoLevel, $versionSwapmode, $rootIsVersion)
    {
        if ($tableName === 'pages' && $uid > 0 && !in_array($uid, $this->excludePageIdList)) {
            if (!$versionSwapmode) {

                // Initialize TemplaVoila API class:
                $apiObj = GeneralUtility::makeInstance(ApiService::class, 'pages');

                // Fetch the content structure of page:
                $contentTreeData = $apiObj->getContentTree('pages', BackendUtility::getRecordRaw('pages', 'uid=' . (int)$uid));
                if ($contentTreeData['tree']['ds_is_found']) {
                    $usedUids = array_keys($contentTreeData['contentElementUsage']);
                    $usedUids[] = 0;

                    // Look up all content elements that are NOT used on this page...
                    $res = static::getDatabaseConnection()->exec_SELECTquery(
                        'uid, header',
                        'tt_content',
                        'pid=' . (int)$uid . ' ' .
                        'AND uid NOT IN (' . implode(',', $usedUids) . ') ' .
                        'AND t3ver_state!=1 ' .
                        'AND t3ver_state!=3 ' .
                        BackendUtility::deleteClause('tt_content') .
                        BackendUtility::versioningPlaceholderClause('tt_content'),
                        '',
                        'uid'
                    );

                    // Traverse, for each find references if any and register them.
                    while (false !== ($row = static::getDatabaseConnection()->sql_fetch_assoc($res))) {

                        // Look up references to elements:
                        $refrows = static::getDatabaseConnection()->exec_SELECTgetRows(
                            'hash',
                            'sys_refindex',
                            'ref_table=' . static::getDatabaseConnection()->fullQuoteStr('tt_content', 'sys_refindex') .
                            ' AND ref_uid=' . (int)$row['uid'] .
                            ' AND deleted=0'
                        );

                        // Look up TRANSLATION references FROM this element to another content element:
                        $isATranslationChild = false;
                        $refrows_From = static::getDatabaseConnection()->exec_SELECTgetRows(
                            'ref_uid',
                            'sys_refindex',
                            'tablename=' . static::getDatabaseConnection()->fullQuoteStr('tt_content', 'sys_refindex') .
                            ' AND recuid=' . (int)$row['uid'] .
                            ' AND field=' . static::getDatabaseConnection()->fullQuoteStr('l18n_parent', 'sys_refindex')
                        );
                        // Check if that other record is deleted or not:
                        if ($refrows_From[0] && $refrows_From[0]['ref_uid']) {
                            $isATranslationChild = BackendUtility::getRecord('tt_content', $refrows_From[0]['ref_uid'], 'uid') ? true : false;
                        }

                        // Register elements etc:
                        $this->resultArray['all_unused'][$row['uid']] = [$row['header'], count($refrows)];
                        if ($echoLevel > 2) {
                            echo chr(10) . '            [tx_templavoila_unusedce:] tt_content:' . $row['uid'] . ' was not used on page...';
                        }
                        if (!count($refrows)) {
                            if ($isATranslationChild) {
                                if ($echoLevel > 2) {
                                    echo ' but is a translation of a non-deleted records and so do not delete...';
                                }
                            } else {
                                $this->resultArray['deleteMe'][$row['uid']] = $row['uid'];
                                if ($echoLevel > 2) {
                                    echo ' and can be DELETED';
                                }
                            }
                        } else {
                            if ($echoLevel > 2) {
                                echo ' but is referenced to (' . count($refrows) . ') so do not delete...';
                            }
                        }
                    }
                } else {
                    if ($echoLevel > 2) {
                        echo chr(10) . '            [tx_templavoila_unusedce:] Did not check page - did not have a Data Structure set.';
                    }
                }
            } else {
                if ($echoLevel > 2) {
                    echo chr(10) . '            [tx_templavoila_unusedce:] Did not check page - was on offline page.';
                }
            }
        }
    }

    /**
     * Mandatory autofix function
     * Will run auto-fix on the result array. Echos status during processing.
     *
     * @param array $resultArray Result array from main() function
     */
    public function main_autoFix($resultArray)
    {
        foreach ($resultArray['deleteMe'] as $uid) {
            echo 'Deleting "tt_content:' . $uid . '": ';
            if ($bypass = $this->cli_noExecutionCheck('tt_content:' . $uid)) {
                echo $bypass;
            } else {

                // Execute CMD array:
                $tce = GeneralUtility::makeInstance(DataHandler::class);
                $tce->stripslashes_values = false;
                $tce->start([], []);
                $tce->deleteAction('tt_content', $uid);

                // Return errors if any:
                if (count($tce->errorLog)) {
                    echo '    ERROR from "TCEmain":' . chr(10) . 'TCEmain:' . implode(chr(10) . 'TCEmain:', $tce->errorLog);
                } else {
                    echo 'DONE';
                }
            }
            echo chr(10);
        }
    }
}
