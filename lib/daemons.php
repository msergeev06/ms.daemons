<?php
/**
 * Работа с демонами
 *
 * @package Ms\Daemons
 * @subpackage Lib
 * @author Mikhail Sergeev <msergeev06@gmail.com>
 * @copyright 2018 Mikhail Sergeev
 */

namespace Ms\Daemons\Lib;

use Ms\Daemons\Tables;
use Ms\Core\Entity\Application;
use Ms\Core\Lib\IO\Files;
use Ms\Core\Entity\Db\DBResult;
use Ms\Core\Lib\Logs;

class Daemons
{
	/**
	 * Функция возвращает путь к папке демонов. Если папка не существует - создает ее
	 *
	 * @return string
	 */
	public static function getDaemonsPath ()
	{
		$daemonsPath = Application::getInstance()->getSettings()->getModulesRoot().'/ms.daemons/daemons';
		if (!file_exists($daemonsPath))
		{
			Files::createDir($daemonsPath);
			Files::saveFile($daemonsPath.'/.htaccess','Deny From All');
		}

		return $daemonsPath.'/';
	}

	/**
	 * Добавляет запись в лог файл указанного демона
	 *
	 * @param string $daemonName Имя демона
	 * @param string $message    Сообщение
	 */
	public static function log ($daemonName, $message)
	{
		Logs::write2Log($message,'daemon_'.$daemonName,true);
		echo $message;
	}

	/**
	 * Обновляет время изменения файла лога, чтобы он не был удален, как устаревший
	 *
	 * @param string $daemonName Имя демона
	 */
	public static function touchLog ($daemonName)
	{
		$filename = Logs::getLogsDir().'/daemon_'.$daemonName.'.log';
		if (file_exists($filename))
		{
			@touch($filename);
		}
	}

	/**
	 * Управляет работой демонов, запускает упавшие.
	 */
	public static function checkDaemons ()
	{
		//Демоны, которые должны работать
		$arRes = Tables\DaemonsTable::getList(
			array(
				'select' => array('NAME','RUNNING','RESTART','PID'),
				'filter' => array(
					'RUN' => true
				)
			)
		);
		if ($arRes)
		{
			foreach ($arRes as &$daemon)
			{
				//Если процесс считается запущенным
				if ($daemon['RUNNING'])
				{
					//Если установлен PID процесса
					if ((int)$daemon['PID']>0)
					{
						//Если процесс не запущен
						if (!self::isRun($daemon['PID']))
						{
							//Пробуем запустить
							self::running($daemon);
						}
						static::touchLog($daemon['NAME']);
					}
					//Если нет сохраненного значения PID
					else
					{
						//Если процесс не в ожидании перезапуска
						if (!$daemon['RESTART'])
						{
							//Планируем перезапуск
							self::restart($daemon['NAME']);
						}
					}
				}
				//Если процесс остановлен
				else
				{
					//Если PID процесса существует
					if ($daemon['PID']>0)
					{
						//Если процесс запущен
						if (self::isRun($daemon['PID']))
						{
							//Обновляем статус процесса
							self::update($daemon['NAME'],array('RUNNING'=>true));
						}
						//Если не запущен, запускаем
						else
						{
							self::running($daemon);
						}
					}
					//Если PID процесса отсутствует
					else
					{
						//Запускаем процесс
						self::running($daemon);
					}
				}
			}
			unset($daemon);
		}
		unset($arRes);

		//Демоны, которые должны быть выключены
		$arRes = Tables\DaemonsTable::getList(
			array(
				'select' => array('NAME','RUNNING','PID'),
				'filter' => array(
					'RUN' => false
				)
			)
		);
		if ($arRes)
		{
			foreach ($arRes as $daemon)
			{
				if ($daemon['RUNNING'] && $daemon['PID']>0)
				{
					if (!self::isRun($daemon['PID']))
					{
						self::update($daemon['NAME'],array('RUNNING'=>false,'PID'=>0));
					}
				}
				elseif ($daemon['RUNNING'])
				{
					self::update($daemon['NAME'],array('RUNNING'=>false,'PID'=>0));
				}
			}
		}
	}

	/**
	 * Проверяет демонов, которые должны быть запущены при старте системы
	 */
	public static function checkDaemonsOnStartUp ()
	{
		$arRes = Tables\DaemonsTable::getList(
			array(
				'select' => array('NAME','RUN','RUNNING'),
				'filter' => array(
					'RUN_STARTUP' => true
				)
			)
		);
		if ($arRes)
		{
			foreach ($arRes as $daemon)
			{
				if (!$daemon['RUN'] && !$daemon['RUNNING'])
				{
					self::update($daemon['NAME'],array("RUN"=>true));
				}
			}
		}
	}

	/**
	 * Останавливает всех демонов.
	 * Если при проверке были найдены
	 *
	 * @return bool
	 */
	public static function stopAllDaemons ()
	{
		$arRes = Tables\DaemonsTable::getList(
			array(
				'select' => array('NAME','RUN'),
				'filter' => array(
					'RUNNING' => true
				)
			)
		);
		if ($arRes)
		{
			foreach ($arRes as $daemon)
			{
				if ($daemon['RUN'])
				{
					self::update($daemon['NAME'],array("RUN"=>false));
				}
			}

			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Проверяет необходимость перезапуска и остановки определенных демонов
	 *
	 * @param string $daemonName Имя демона
	 *
	 * @return bool
	 */
	public static function needBreak ($daemonName)
	{
		$daemonName = strtolower($daemonName);
		$daemon = Tables\DaemonsTable::getOne(
			array(
				'select' => array('NAME','RUNNING','RESTART','RUN', 'PID'),
				'filter' => array(
					'NAME' => $daemonName
				)
			)
		);
		if ($daemon)
		{
			//Если необходимо перезапустить демона
			if ($daemon['RESTART'])
			{
				$res = self::update(
					$daemonName,
					array(
						'RESTART' => false,
						'PID' => 0,
						'RUNNING' => false
					)
				);
				if ($res->getResult())
				{
					self::log($daemonName,'Daemon planned restart');
					return true;
				}
			}
			//Если необходимо остановить демона
			elseif (!$daemon['RUN'])
			{
				$res = self::update(
					$daemonName,
					array(
						'PID' => 0,
						'RUNNING' => false
					)
				);
				if ($res->getResult())
				{
					self::log($daemonName,'Daemon planned stop');
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Устанавливает флаг необходимости запуска демона
	 *
	 * @param string $daemonName Имя демона
	 *
	 * @return resource|bool
	 */
	public static function run ($daemonName)
	{
		return self::update($daemonName,array('RUN'=>true))->getResult();
	}

	/**
	 * Устанавливает флаг необходимости перезапуска демона
	 *
	 * @param string $daemonName Имя демона
	 *
	 * @return resource
	 */
	public static function restart ($daemonName)
	{
		return self::update($daemonName,array('RESTART'=>true))->getResult();
	}

	/**
	 * Устанавливает флаг необходимости остановки демона
	 *
	 * @param string $daemonName Имя демона
	 *
	 * @return resource|bool
	 */
	public static function stop ($daemonName)
	{
		return self::update($daemonName,array('RUN'=>false))->getResult();
	}

	/**
	 * Отражает в БД остановку демона
	 *
	 * @param string $daemonName Имя демона
	 */
	public static function stopped ($daemonName)
	{
		$arRes = Tables\DaemonsTable::getOne(
			array(
				'select' => array('NAME'),
				'filter' => array('NAME'=>$daemonName)
			)
		);

		if (isset($arRes['NAME']))
		{
			self::update($arRes['NAME'],array('RUNNING'=>false));
		}
	}

	/**
	 * Добавляет запись о новом демоне в БД
	 *
	 * @param array $arParams Параметры демона
	 *
	 * @return bool|int
	 */
	public static function addNewDaemon (array $arParams)
	{
		if (empty($arParams) || !isset($arParams['NAME']))
		{
			return false;
		}

		$arParams['NAME'] = strtolower($arParams['NAME']);

		$arRes = Tables\DaemonsTable::getOne(array (
			'select' => 'NAME',
			'filter' => array ('NAME'=>$arParams['NAME'])
		));
		if ($arRes)
		{
			return false;
		}
		unset($arRes);

		$arAdd = array(
			'NAME' => $arParams['NAME']
		);
		if (isset($arParams['NOTE']))
		{
			if(strlen($arParams['NOTE'])>255)
			{
				$arParams['NOTE'] = mb_substr($arParams['NOTE'],0,255);
			}

			$arAdd['NOTE'] = $arParams['NOTE'];
		}
		if (isset($arParams['RUN']))
		{
			$arAdd['RUN'] = !!$arParams['RUN'];
		}
		if (isset($arParams['RUN_STARTUP']))
		{
			$arAdd['RUN_STARTUP'] = !!$arParams['RUN_STARTUP'];
		}

		$res = Tables\DaemonsTable::add($arAdd);
		if ($res->getResult())
		{
			return $arParams['NAME'];
		}

		return false;
	}

	/**
	 * Служебная функция. Проверяет запущен ли указанный демон
	 *
	 * @param int $PID PID процесса демона
	 *
	 * @return bool
	 */
	private static function isRun ($PID)
	{
		$command = 'ps -p '.$PID;
		exec($command,$op);
		if (!isset($op[1]))
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Служебная функция, проверяющая правильность запуска демона.
	 *
	 * @param array $daemon Массив параметров демона
	 */
	private static function running (array $daemon)
	{
		$res = self::start($daemon['NAME']);
		if ($res !== false)
		{
			//Процесс запущен
			if (!$res['RES'])
			{
				//Если данные не записались в базу убиваем процесс
				self::kill($res['PID']);
				self::update(
					$daemon['NAME'],
					array(
						'PID' => 0,
						'RUNNING' => false
					)
				);
			}
		}
	}

	/**
	 * Служебная функция. Стартует указанного демона
	 *
	 * @param string $name Имя демона
	 *
	 * @return array|bool
	 */
	private static function start ($name)
	{
		$daemonsPath = self::getDaemonsPath();
		//$outFilename = Files::getLogsDir().'log-daemon_'.$name.'-'.date('Ymd').'.txt';
		$outFilename = '/dev/null';

		$command = 'nohup php -f '.$daemonsPath.'/daemon_'.strtolower($name).'.php > '.$outFilename.' 2>&1 & echo $!';
		exec($command ,$op);
		if (intval($op[0])>0)
		{
			$res = self::update(
				strtolower($name),
				array(
					"PID" => intval($op[0]),
					"RUNNING" => true
				)
			);
			if (!$res->getResult())
			{
				self::log($name,'Not save in DB daemon PID ['.intval($op[0]).']');
			}

			return array('PID'=>intval($op[0]),'RUNNING'=>true,'RES'=>$res->getResult());
		}
		else
		{
			return false;
		}
	}

	/**
	 * Служебная функция облегающая обновление информации
	 *
	 * @param $primary
	 * @param $arUpdate
	 *
	 * @return DBResult
	 */
	private static function update ($primary, $arUpdate)
	{
		return Tables\DaemonsTable::update($primary,$arUpdate);
	}

	/**
	 * Функция убивает демона shell функцией kill
	 *
	 * @param int $PID PID процесса демона
	 *
	 * @return bool
	 */
	private static function kill ($PID)
	{
		$command = 'kill '.intval($PID);
		exec($command);
		if (self::isRun($PID) === false)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}