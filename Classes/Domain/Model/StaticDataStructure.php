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

namespace Schnitzler\Templavoila\Domain\Model;

use Schnitzler\Templavoila\Domain\Repository\DataStructureRepository;
use Schnitzler\Templavoila\Traits\DatabaseConnection;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class to provide unique access to static datastructure
 *
 *
 */
class StaticDataStructure extends AbstractDataStructure
{
    use DatabaseConnection;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @throws \InvalidArgumentException
     *
     * @param int $key
     */
    public function __construct($key)
    {
        $conf = DataStructureRepository::getStaticDatastructureConfiguration();

        if (!isset($conf[$key])) {
            throw new \InvalidArgumentException(
                'Argument was supposed to be an existing datastructure',
                1283192644
            );
        }

        $this->filename = $conf[$key]['path'];

        $this->setLabel($conf[$key]['title']);
        $this->setScope($conf[$key]['scope']);
        // path relative to typo3 maindir
        $this->setIcon('../' . $conf[$key]['icon']);
    }

    /**
     * @return string
     */
    public function getStoragePids()
    {
        $pids = [];
        $toList = (array)static::getDatabaseConnection()->exec_SELECTgetRows(
            'tx_templavoila_tmplobj.uid,tx_templavoila_tmplobj.pid',
            'tx_templavoila_tmplobj',
            'tx_templavoila_tmplobj.datastructure=' . static::getDatabaseConnection()->fullQuoteStr($this->filename, 'tx_templavoila_tmplobj') . BackendUtility::deleteClause('tx_templavoila_tmplobj')
        );
        foreach ($toList as $toRow) {
            $pids[$toRow['pid']]++;
        }

        return implode(',', array_keys($pids));
    }

    /**
     * @return string - the filename
     */
    public function getKey()
    {
        return $this->filename;
    }

    /**
     * Provides the datastructure configuration as XML
     *
     * @return string
     */
    public function getDataprotXML()
    {
        $xml = '';
        $file = GeneralUtility::getFileAbsFileName($this->filename);
        if (is_readable($file)) {
            $xml = file_get_contents($file);
        } else {
            // @todo find out if that happens and whether there's a "useful" reaction for that
        }

        return $xml;
    }

    /**
     * Determine whether the current user has permission to create elements based on this
     * datastructure or not - not really useable for static datastructure but relevant for
     * the overall system
     *
     * @param mixed $parentRow
     * @param mixed $removeItems
     *
     * @return bool
     */
    public function isPermittedForUser(array $parentRow = [], array $removeItems = [])
    {
        return true;
    }

    /**
     * Enables to determine whether this element is based on a record or on a file
     * Required for view-related tasks (edit-icons)
     *
     * @return bool
     */
    public function isFilebased()
    {
        return true;
    }

    /**
     * Retrieve the filereference of the template
     *
     * @return int
     */
    public function getTstamp()
    {
        $tstamp = 0;
        $file = GeneralUtility::getFileAbsFileName($this->filename);
        if (is_readable($file)) {
            $tstamp = filemtime($file);
        }

        return $tstamp;
    }

    /**
     * Retrieve the filereference of the template
     *
     * @return int
     */
    public function getCrdate()
    {
        $tstamp = 0;
        $file = GeneralUtility::getFileAbsFileName($this->filename);
        if (is_readable($file)) {
            $tstamp = filectime($file);
        }

        return $tstamp;
    }

    /**
     * Retrieve the filereference of the template
     *
     * @return int
     */
    public function getCruser()
    {
        return 0;
    }

    /**
     * @param void
     *
     * @return string
     */
    public function getSortingFieldValue()
    {
        return $this->getLabel(); // required to resolve LLL texts
    }

    /**
     * @return bool
     */
    public function hasBackendGridTemplateName()
    {
        return false; // todo: implement correctly
    }

    /**
     * @return string
     */
    public function getBackendGridTemplateName()
    {
        return ''; // todo: implement correctly
    }
}
