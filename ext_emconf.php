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

$EM_CONF[$_EXTKEY] = [
    'title' => 'TemplaVoila!',
    'description' => 'Point-and-click, popular and easy template engine for TYPO3. Public free support is provided only through TYPO3 mailing lists! Contact by e-mail for commercial support.',
    'category' => 'misc',
    'version' => '7.6.0',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => 'uploads/tx_templavoila/',
    'clearcacheonload' => 1,
    'author' => 'Alexander Schnitzler',
    'author_company' => 'Schnitzler Softwarelösungen',
    'constraints' => [
        'depends' => [
            'typo3' => '7.6.0-7.6.99'
        ],
        'conflicts' => [
            'kb_tv_clipboard' => '-0.1.0',
            'templavoila_cw' => '-0.1.0',
            'eu_tradvoila' => '-0.0.2',
            'me_templavoilalayout' => '',
            'me_templavoilalayout2' => '',
        ],
        'suggests' => [],
    ],
    '_md5_values_when_last_written' => '',
];
