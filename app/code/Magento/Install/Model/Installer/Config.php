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
 * @package     Magento_Install
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Install\Model\Installer;

/**
 * Config installer
 */
class Config extends \Magento\Install\Model\Installer\AbstractInstaller
{
    const TMP_INSTALL_DATE_VALUE= 'd-d-d-d-d';
    const TMP_ENCRYPT_KEY_VALUE = 'k-k-k-k-k';

    /**
     * Path to local configuration file
     *
     * @var string
     */
    protected $_localConfigFile = 'local.xml';

    /**
     * @var \Magento\App\RequestInterface
     */
    protected $_request;

    protected $_configData = array();

    /**
     * @var \Magento\Filesystem
     */
    protected $_filesystem;

    /**
     * @var \Magento\Filesystem\Directory\Write
     */
    protected $_configDirectory;

    /**
     * Store Manager
     *
     * @var \Magento\Core\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @param \Magento\Install\Model\Installer $installer
     * @param \Magento\App\RequestInterface $request
     * @param \Magento\Filesystem $filesystem
     * @param \Magento\Core\Model\StoreManagerInterface $storeManager
     * @param \Magento\Message\ManagerInterface $messageManager
     */
    public function __construct(
        \Magento\Install\Model\Installer $installer,
        \Magento\App\RequestInterface $request,
        \Magento\Filesystem $filesystem,
        \Magento\Core\Model\StoreManagerInterface $storeManager,
        \Magento\Message\ManagerInterface $messageManager
    ) {
        parent::__construct($installer);
        $this->_request = $request;
        $this->_storeManager = $storeManager;
        $this->_filesystem = $filesystem;
        $this->_configDirectory = $filesystem->getDirectoryWrite(\Magento\Filesystem::CONFIG);
        $this->messageManager = $messageManager;
    }

    public function setConfigData($data)
    {
        if (is_array($data)) {
            $this->_configData = $data;
        }
        return $this;
    }

    public function getConfigData()
    {
        return $this->_configData;
    }

    /**
     * Generate installation data and record them into local.xml using local.xml.template
     */
    public function install()
    {
        $data = $this->getConfigData();

        $defaults = array(
            'root_dir' => $this->_filesystem->getPath(\Magento\Filesystem::ROOT),
            'app_dir'  => $this->_filesystem->getPath(\Magento\Filesystem::APP),
            'var_dir'  => $this->_filesystem->getPath(\Magento\Filesystem::VAR_DIR),
            'base_url' => $this->_request->getDistroBaseUrl(),
        );
        foreach ($defaults as $index => $value) {
            if (!isset($data[$index])) {
                $data[$index] = $value;
            }
        }

        if (isset($data['unsecure_base_url'])) {
            $data['unsecure_base_url'] .= substr($data['unsecure_base_url'], -1) != '/' ? '/' : '';
            if (strpos($data['unsecure_base_url'], 'http') !== 0) {
                $data['unsecure_base_url'] = 'http://' . $data['unsecure_base_url'];
            }
            if (!$this->_getInstaller()->getDataModel()->getSkipBaseUrlValidation()) {
                $this->_checkUrl($data['unsecure_base_url']);
            }
        }
        if (isset($data['secure_base_url'])) {
            $data['secure_base_url'] .= substr($data['secure_base_url'], -1) != '/' ? '/' : '';
            if (strpos($data['secure_base_url'], 'http') !== 0) {
                $data['secure_base_url'] = 'https://' . $data['secure_base_url'];
            }

            if (!empty($data['use_secure'])
                && !$this->_getInstaller()->getDataModel()->getSkipUrlValidation()) {
                $this->_checkUrl($data['secure_base_url']);
            }
        }

        $data['date']   = self::TMP_INSTALL_DATE_VALUE;
        $data['key']    = self::TMP_ENCRYPT_KEY_VALUE;
        $data['var_dir'] = $data['root_dir'] . '/var';

        $data['use_script_name'] = isset($data['use_script_name']) ? 'true' : 'false';

        $this->_getInstaller()->getDataModel()->setConfigData($data);

        $contents = $this->_configDirectory->readFile('local.xml.template');
        foreach ($data as $index => $value) {
            $contents = str_replace('{{' . $index . '}}', '<![CDATA[' . $value . ']]>', $contents);
        }

        $this->_configDirectory->writeFile($this->_localConfigFile, $contents);
        $this->_configDirectory->changePermissions($this->_localConfigFile, 0777);
    }

    public function getFormData()
    {
        $uri = \Zend_Uri::factory($this->_storeManager->getStore()->getBaseUrl('web'));

        $baseUrl = $uri->getUri();
        if ($uri->getScheme() !== 'https') {
            $uri->setPort(null);
            $baseSecureUrl = str_replace('http://', 'https://', $uri->getUri());
        } else {
            $baseSecureUrl = $uri->getUri();
        }

        $data = new \Magento\Object();
        $data->setDbHost('localhost')
            ->setDbName('magento')
            ->setDbUser('')
            ->setDbModel('mysql4')
            ->setDbPass('')
            ->setSecureBaseUrl($baseSecureUrl)
            ->setUnsecureBaseUrl($baseUrl)
            ->setBackendFrontname('backend')
            ->setEnableCharts('1')
        ;
        return $data;
    }

    /**
     * Check validity of a base URL
     *
     * @param string $baseUrl
     * @throws \Magento\Core\Exception
     * @throws \Exception
     */
    protected function _checkUrl($baseUrl)
    {
        try {
            $directory = $this->_filesystem->getDirectoryRead(\Magento\Filesystem::PUB_LIB);
            $files = $directory->search('/.+\.(html?|js|css|gif|jpe?g|png)$/');

            $staticFile = isset($files[0]) ? $files[0] : null;
            $staticUrl = $baseUrl . $this->_filesystem->getUri(\Magento\Filesystem::PUB_LIB) . '/' . $staticFile;
            $client = new \Magento\HTTP\ZendClient($staticUrl);
            $response = $client->request('GET');
        } catch (\Exception $e){
            $this->messageManager->addError(
                __('The URL "%1" is not accessible.', $baseUrl)
            );
            throw $e;
        }
        if ($response->getStatus() != 200) {
            $this->messageManager->addError(
                __('The URL "%1" is invalid.', $baseUrl)
            );
            throw new \Magento\Core\Exception(__('Response from the server is invalid.'));
        }
    }

    public function replaceTmpInstallDate($date = 'now')
    {
        $stamp    = strtotime((string) $date);
        $localXml = $this->_configDirectory->readFile($this->_localConfigFile);
        $localXml = str_replace(self::TMP_INSTALL_DATE_VALUE, date('r', $stamp), $localXml);
        $this->_configDirectory->writeFile($this->_localConfigFile, $localXml);

        return $this;
    }

    public function replaceTmpEncryptKey($key)
    {
        $localXml = $this->_configDirectory->readFile($this->_localConfigFile);
        $localXml = str_replace(self::TMP_ENCRYPT_KEY_VALUE, $key, $localXml);
        $this->_configDirectory->writeFile($this->_localConfigFile, $localXml);

        return $this;
    }
}
