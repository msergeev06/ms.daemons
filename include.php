<?php
/**
 * Основной подключаемый файл модуля
 *
 * @package Ms\Daemons
 * @author Mikhail Sergeev <msergeev06@gmail.com>
 * @copyright 2018 Mikhail Sergeev
 */

use Ms\Core\Lib\Loader;
use Ms\Core\Entity\Application;

$app = Application::getInstance();

$moduleName = 'ms.daemons';
$moduleRoot = $app->getSettings()->getModulesRoot().'/'.$moduleName;
$namespaceRoot = 'Ms\Daemons';

Loader::AddAutoLoadClasses(
	array(
		/** Lib */
		$namespaceRoot.'\Lib\Daemons' => $moduleRoot.'/lib/daemons.php',
		/** Tables */
		$namespaceRoot.'\Tables\DaemonsTable' => $moduleRoot.'/tables/daemons.php'
	)
);

