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
 * @category    Magento
 * @package     Magento_Newsletter
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Newsletter template resource model
 *
 * @category    Magento
 * @package     Magento_Newsletter
 * @author      Magento Core Team <core@magentocommerce.com>
 */
namespace Magento\Newsletter\Model\Resource;

class Template extends \Magento\Core\Model\Resource\Db\AbstractDb
{
    /**
     * Date
     *
     * @var \Magento\Core\Model\Date
     */
    protected $_date;

    /**
     * @param \Magento\App\Resource $resource
     * @param \Magento\Core\Model\Date $date
     */
    public function __construct(\Magento\App\Resource $resource, \Magento\Core\Model\Date $date)
    {
        parent::__construct($resource);
        $this->_date = $date;
    }

    /**
     * Initialize connection
     *
     */
    protected function _construct()
    {
        $this->_init('newsletter_template', 'template_id');
    }

    /**
     * Load an object by template code
     *
     * @param \Magento\Newsletter\Model\Template $object
     * @param string $templateCode
     * @return \Magento\Newsletter\Model\Resource\Template
     */
    public function loadByCode(\Magento\Newsletter\Model\Template $object, $templateCode)
    {
        $read = $this->_getReadAdapter();
        if ($read && !is_null($templateCode)) {
            $select = $this->_getLoadSelect('template_code', $templateCode, $object)
                ->where('template_actual = :template_actual');
            $data = $read->fetchRow($select, array('template_actual'=>1));

            if ($data) {
                $object->setData($data);
            }
        }

        $this->_afterLoad($object);

        return $this;
    }

    /**
     * Check usage of template in queue
     *
     * @param \Magento\Newsletter\Model\Template $template
     * @return boolean
     */
    public function checkUsageInQueue(\Magento\Newsletter\Model\Template $template)
    {
        if ($template->getTemplateActual() !== 0 && !$template->getIsSystem()) {
            $select = $this->_getReadAdapter()->select()
                ->from($this->getTable('newsletter_queue'), new \Zend_Db_Expr('COUNT(queue_id)'))
                ->where('template_id = :template_id');

            $countOfQueue = $this->_getReadAdapter()->fetchOne($select, array('template_id'=>$template->getId()));

            return $countOfQueue > 0;
        } elseif ($template->getIsSystem()) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Check usage of template code in other templates
     *
     * @param \Magento\Newsletter\Model\Template $template
     * @return boolean
     */
    public function checkCodeUsage(\Magento\Newsletter\Model\Template $template)
    {
        if ($template->getTemplateActual() != 0 || is_null($template->getTemplateActual())) {
            $bind = array(
                'template_id'     => $template->getId(),
                'template_code'   => $template->getTemplateCode(),
                'template_actual' => 1
            );
            $select = $this->_getReadAdapter()->select()
                ->from($this->getMainTable(), new \Zend_Db_Expr('COUNT(template_id)'))
                ->where('template_id != :template_id')
                ->where('template_code = :template_code')
                ->where('template_actual = :template_actual');

            $countOfCodes = $this->_getReadAdapter()->fetchOne($select, $bind);

            return $countOfCodes > 0;
        } else {
            return false;
        }
    }

    /**
     * Perform actions before object save
     *
     * @param \Magento\Core\Model\AbstractModel $object
     * @return \Magento\Newsletter\Model\Resource\Template
     * @throws \Magento\Core\Exception
     */
    protected function _beforeSave(\Magento\Core\Model\AbstractModel $object)
    {
        if ($this->checkCodeUsage($object)) {
            throw new \Magento\Core\Exception(__('Duplicate template code'));
        }

        if (!$object->hasTemplateActual()) {
            $object->setTemplateActual(1);
        }
        if (!$object->hasAddedAt()) {
            $object->setAddedAt($this->_date->gmtDate());
        }
        $object->setModifiedAt($this->_date->gmtDate());

        return parent::_beforeSave($object);
    }
}
