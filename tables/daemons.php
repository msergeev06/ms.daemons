<?php
/**
 * Класс описания таблицы ms_daemons_daemons
 *
 * @package Ms\Daemons
 * @subpackage Tables
 * @author Mikhail Sergeev <msergeev06@gmail.com>
 * @copyright 2018 Mikhail Sergeev
 */

namespace Ms\Daemons\Tables;

use Ms\Core\Lib\DataManager;
use Ms\Core\Entity\Db\Fields;
use Ms\Core\Lib\Loc;

Loc::includeLocFile(__FILE__);

class DaemonsTable extends DataManager
{
	public static function getTableTitle ()
	{
		return Loc::getModuleMessage('ms.daemons','table_title');//Демоны
	}

	protected static function getMap ()
	{
		return array (
			new Fields\StringField('NAME',array (
				'primary' => true,
				'title' => Loc::getModuleMessage('ms.daemons','field_name')//Уникальное имя демона
			)),
			new Fields\StringField('NOTE',array (
				'title' => Loc::getModuleMessage('ms.daemons','field_note')//Описание демона
			)),
			new Fields\BooleanField('RUNNING',array (
				'required' => true,
				'default_create' => false,
				'default_insert' => false,
				'title' => Loc::getModuleMessage('ms.daemons','field_running')//Флаг, запущен ли демон
			)),
			new Fields\BooleanField('RUN',array (
				'required' => true,
				'default_create' => true,
				'default_insert' => true,
				'title' => Loc::getModuleMessage('ms.daemons','field_run')//Флаг запуска/остановки
			)),
			new Fields\BooleanField('RESTART',array (
				'required' => true,
				'default_create' => false,
				'default_insert' => false,
				'title' => Loc::getModuleMessage('ms.daemons','field_restart')//Флаг необходимости перезапуска
			)),
			new Fields\BooleanField('RUN_STARTUP',array (
				'required' => true,
				'default_create' => true,
				'default_insert' => true,
				'title' => Loc::getModuleMessage('ms.daemons','field_run_startup')//Флаг запуска при старте системы
			)),
			new Fields\IntegerField('PID',array (
				'title' => Loc::getModuleMessage('ms.daemons','field_pid')//PID процесса ОС
			)),
			new Fields\DateTimeField('STARTED',array (
				'title' => Loc::getModuleMessage('ms.daemons','field_started')//Время запуска демона
			))
		);
	}

	public static function getAdditionalCreateSql ()
	{
		return "DELIMITER //\n"
			."CREATE TRIGGER `before_update_".static::getTableName()."`\n"
			."BEFORE UPDATE ON `".static::getTableName()."` FOR EACH ROW\n"
			."BEGIN\n\t"
			."IF NEW.RUNNING LIKE 'Y' THEN\n\t\t"
			."SET NEW.STARTED = NOW();\n\t"
			."ELSEIF 1=1 THEN\n\t\t"
			."SET NEW.STARTED = NULL;\n\t"
			."END IF;\n"
			."END//\n"
			."DELIMITER ;";
	}

	public static function getAdditionalDeleteSql ()
	{
		return "DROP TRIGGER IF EXISTS `before_update_".static::getTableName()."`;";
	}
}