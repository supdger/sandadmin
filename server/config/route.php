<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use app\controller\FileController;
use Webman\Route;

// Public controllers must opt in explicitly; installed plugins register their
// own routes through Webman's plugin configuration.
Route::disableDefaultRoute();

Route::post('/api/files', [FileController::class, 'store']);
Route::post('/api/files/{id}/usages', [FileController::class, 'registerUsage']);
Route::delete('/api/files/{id}/usages', [FileController::class, 'unregisterUsage']);
Route::delete('/api/files/{id}', [FileController::class, 'destroy']);
