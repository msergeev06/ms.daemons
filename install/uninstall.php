<?php
/**
 * Файл удаления модуля
 *
 * @package Ms\Daemons
 * @author Mikhail Sergeev <msergeev06@gmail.com>
 * @copyright 2018 Mikhail Sergeev
 */

use Ms\Core\Lib\Installer;

return (Installer::dropModuleTables('ms.daemons'));