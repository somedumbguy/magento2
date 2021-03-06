<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Magento
 * @package    Tools
 * @copyright  Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Tools\Migration\System\Configuration\Logger;

class Factory
{
    /**
     * Get logger instance
     *
     * @param string $loggerType
     * @param string $filePath
     * @param \Magento\Tools\Migration\System\FileManager $fileManager
     * @return \Magento\Tools\Migration\System\Configuration\AbstractLogger
     */
    public function getLogger($loggerType, $filePath, \Magento\Tools\Migration\System\FileManager $fileManager)
    {
        /** @var \Magento\Tools\Migration\System\Configuration\AbstractLogger $loggerInstance  */
        $loggerInstance = null;
        switch ($loggerType) {
            case 'file':
                $loggerInstance =
                    new \Magento\Tools\Migration\System\Configuration\Logger\File($filePath, $fileManager);
                break;
            default:
                $loggerInstance = new \Magento\Tools\Migration\System\Configuration\Logger\Console();
                break;
        }

        return $loggerInstance;
    }
}
