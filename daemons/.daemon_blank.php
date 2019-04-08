<?php
/**
 * Образец файла-демона
 *
 * @package Ms\Daemons
 * @author Mikhail Sergeev <msergeev06@gmail.com>
 * @copyright 2018 Mikhail Sergeev
 */

$daemonName = 'blank';  /**<-- Обязательно изменить на уникальное имя демона*/
$sleep = 5; /**<-- Здесь можно задать свою паузу между итерациями. Более длительная пауза разгрузит систему */

/* Общие команды для всех демонов */
define('MS_NO_CHECK_AGENTS',true);
define('MS_NO_AUTH',true);
include_once ($_SERVER['DOCUMENT_ROOT']."/ms/core/prolog_before.php");

use Ms\Daemons\Lib;
use Ms\Core\Lib\Loader;
use Ms\Core\Lib\Logs;

set_time_limit(0);

if (!Loader::includeModule('ms.daemons')
	/**<-- Добавьте сюда свой модуль, для которого создается демон */
)
{
	Logs::setWarning('Ошибка запуска демона ['.$daemonName.']. Необходимый модуль [ms.daemon] не установлен');
	return;
}

$bStopped = false;
Lib\Daemons::log($daemonName,'Daemon started');
/* end Общие команды для всех демонов*/

while (1)
{
	//Здесь размещается вызов метода демона


	/* Общие команды для всех демонов */
	if (Lib\Daemons::needBreak($daemonName))
	{
		$bStopped = true;
		break;
	}
	sleep($sleep);
	/* end Общие команды для всех демонов*/
}

/* Общие команды для всех демонов */
if (!$bStopped)
{
	Lib\Daemons::stopped($daemonName);
	Lib\Daemons::log($daemonName,"Неожиданное завершение работы демона");
	Logs::setWarning('Неожиданное завершение работы демона ['.$daemonName.']');
}
/* end Общие команды для всех демонов*/
