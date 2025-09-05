<?php
declare(strict_types=1);

/**
 * This file is part of the CSas Statement Tools package
 *
 * https://github.com/VitexSoftware/file2sharepoint
 *
 * (c) Vítězslav Dvořák <info@vitexsoftware.cz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once '../vendor/autoload.php';

\define('APP_NAME', 'abo2json');

if ($argc === 1) {
    echo $argv[0]." <source/files/path/*.*> <Sharepoint/dest/folder/path/> [/path/to/config/.env] \n";
}

