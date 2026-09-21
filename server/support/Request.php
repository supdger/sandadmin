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

namespace support;

/**
 * Class Request
 * @package support
 */
class Request extends \Webman\Http\Request
{

    /**
     * Read multiple request values using SandAdmin's field/default contract.
     *
     * @param array<int, string|array{0: string|array{0: string, 1: string}, 1?: mixed}> $params
     * @return array<string, mixed>
     */
    public function more(array $params): array
    {
        $values = [];
        foreach ($params as $param) {
            if (!is_array($param)) {
                $values[$param] = $this->input($param);
                continue;
            }

            $default = $param[1] ?? '';
            if (is_array($param[0])) {
                $name = $param[0][0] . '/' . $param[0][1];
                $key = $param[0][0];
            } else {
                $name = $param[0];
                $key = $param[0];
            }
            $values[$key] = $this->input($name, $default);
        }
        return $values;
    }

}
