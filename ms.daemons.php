<?php
/**
 * Описание модуля ms.daemons
 *
 * @package Ms\Daemons
 * @author Mikhail Sergeev <msergeev06@gmail.com>
 * @copyright 2018 Mikhail Sergeev
 */

use Ms\Core\Lib\Loc;

Loc::includeLocFile(__FILE__);

return array(
	'NAME' => Loc::getModuleMessage('ms.daemons','name'),
	'DESCRIPTION' => Loc::getModuleMessage('ms.daemons','description'),
	'URL' => 'https://dobrozhil.ru/modules/ms/dates/',
	'DOCS' => 'http://docs.dobrozhil.ru',
	'AUTHOR' => Loc::getModuleMessage('ms.daemons','author'),
	'AUTHOR_EMAIL' => 'msergeev06@gmail.com'
);