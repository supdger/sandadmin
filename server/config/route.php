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

use Sandadmin\Http\JobController;
use Webman\Route;

// Public controllers must opt in explicitly; installed plugins register their
// own routes through Webman's plugin configuration.
Route::disableDefaultRoute();

$jobRoute = Route::get('/api/jobs/{id}', [JobController::class, 'show']);
if (class_exists(\plugin\sandadmin\app\middleware\CheckLogin::class)) {
    $jobRoute->middleware([\plugin\sandadmin\app\middleware\CheckLogin::class]);
}
