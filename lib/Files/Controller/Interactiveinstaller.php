<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv2.1 (or at your option, any later version).
 * @package Files
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

class Files_Controller_Interactiveinstaller extends Zikula_InteractiveInstaller
{
    public function postInitialize()
    {
        $this->view->setCaching(false);
    }

     /**
     * Initialise the interactive install system for the Files module. Checks if the needed folders exists and they are writeable
     * @author Albert Pérez Monfort (aperezm@xtec.cat)
     * @return If the files folder and users folder are not created and writeable it is not possible to install
     */
    public function install()
    {
    	if (!SecurityUtil::checkPermission('Files::', '::', ACCESS_ADMIN)) {
    		return LogUtil::registerPermissionError();
    	}
        $this->view->assign('step', 'info');
        return $this->view->fetch('Files_init.htm');
    }
    
    public function form($args)
    {
        $file1 = FormUtil::getPassedValue('file1', isset($args['file1']) ? $args['file1'] : null, 'GET');
        $file2 = FormUtil::getPassedValue('file2', isset($args['file2']) ? $args['file2'] : 'users', 'GET');
        if (!SecurityUtil::checkPermission('Files::', '::', ACCESS_ADMIN)){
            return LogUtil::registerPermissionError();
        }
        if($GLOBALS['PNConfig']['Multisites']['multi'] == 1) {
            $filesRealPath = 'files';
            $createdFilesFolder = true;
        } else {
    	    // get server file root
            if($file1 == null) {
                $filesRoot = $_SERVER['DOCUMENT_ROOT'];
                $filesRealPath = substr($filesRoot, 0 ,  strrpos($filesRoot, '/')) . '/filesFolder';
            } else {
                $filesRealPath = $file1;
            }
            $createdFilesFolder = false;
        }
        $this->view->assign('filesRealPath', $filesRealPath);
        $this->view->assign('usersFolder', $file2);
        $this->view->assign('createdFilesFolder', $createdFilesFolder);
        $this->view->assign('step', 'form');
        return $this->view->fetch('Files_init.htm');
    }
    
    /**
     * Step 1 - Check if the needed files exists and if they are writeable
     * @author Albert Pérez Monfort (aperezm@xtec.cat)
     * @return if they exist and are writeable user can jump to step 2
     */
    public function update()
    {
        $filesRealPath = FormUtil::getPassedValue('filesRealPath', isset($args['filesRealPath']) ? $args['filesRealPath'] : null, 'POST');
        $usersFolder = FormUtil::getPassedValue('usersFolder', isset($args['usersFolder']) ? $args['usersFolder'] : null, 'POST');
    	if (!SecurityUtil::checkPermission('Files::', '::', ACCESS_ADMIN)) {
    		return LogUtil::registerPermissionError();
    	}
    	$multisites = false;
    	if($GLOBALS['PNConfig']['Multisites']['multi'] == 1) {
    		// create the needed folders for the site
    		$siteDNS = (isset($_GET['siteDNS']) ? DataUtil::formatForOS($_GET['siteDNS']) : null);
    		$filesRealPath = $GLOBALS['PNConfig']['Multisites']['filesRealPath'] . '/' . $siteDNS . $GLOBALS['PNConfig']['Multisites']['siteFilesFolder'];
    		if(!FileUtil::mkdirs($filesRealPath . '/' . $usersFolder, 0777, true)) {
                LogUtil::registerError($this->__('Directory creation error') . ': ' . $usersFolder);
                return false;
    		}
    		$multisites = true;
    	}
        // check if the needed files are located in the correct places and they are writeable
        $file1 = false;
        $file2 = false;
        $fileWriteable1 = false;
        $fileWriteable2 = false;
        $path = $filesRealPath;
        if (file_exists($path)) {
            $file1 = true;
        }
        if (is_writeable($path)) {
            $fileWriteable1 = true;
        }
        $path = $filesRealPath . '/' . $usersFolder;
        if (file_exists($path)) {
            $file2 = true;
        }
        if (is_writeable($path)) {
            $fileWriteable2 = true;
        }
        if($fileWriteable1 && $fileWriteable2) {
            ModUtil::setVar('Files', 'folderPath', $filesRealPath);
            ModUtil::setVar('Files', 'usersFolder', $usersFolder);
        }
        $this->view->assign('filesRealPath', $filesRealPath);
        $this->view->assign('usersFolder', $usersFolder);
        $this->view->assign('file1', $file1);
        $this->view->assign('file2', $file2);
        $this->view->assign('multisites', $multisites);
        $this->view->assign('fileWriteable1', $fileWriteable1);
        $this->view->assign('fileWriteable2', $fileWriteable2);
        $this->view->assign('step', 'check');
        return $this->view->fetch('Files_init.htm');
    }

    /**
    * Proceed with the module installation
    * @author Albert Pérez Monfort (aperezm@xtec.cat)
    * @return If the files folder and users folder are not created and writeable it is not possible to install
    */
    public function finalInstall()
    {
    	if (!SecurityUtil::checkPermission('Files::', '::', ACCESS_ADMIN)) {
    		return LogUtil::registerPermissionError();
    	}
        // set content of the files .htaccess and .locked
        $htaccessContent = "# Avoid direct web access to folder files\r\nOrder deny,allow\r\nDeny from all\r\n";
        $lockedContent = "# Avoid direct web access with the file file.php\r\n";
        // Create module table
        if (!DBUtil::createTable('Files')) return false;
        //Create indexes
        $pntable = DBUtil::getTables();
        $c = $pntable['Files_column'];
        DBUtil::createIndex($c['userId'], 'Files', 'userId');
        // create security files
        FileUtil::writeFile(ModUtil::getVar('Files', 'folderPath') . '/.htaccess', $htaccessContent, true);
        FileUtil::writeFile(ModUtil::getVar('Files', 'folderPath') . '/.locked', $lockedContent, true);
        FileUtil::writeFile(ModUtil::getVar('Files', 'folderPath') . '/' . ModUtil::getVar('Files', 'usersFolder') . '/.htaccess', $htaccessContent, true);
        FileUtil::writeFile(ModUtil::getVar('Files', 'folderPath') . '/' . ModUtil::getVar('Files', 'usersFolder') . '/.locked', $lockedContent, true);
        //Create module vars
        ModUtil::setVar('Files', 'showHideFiles', '0');
        ModUtil::setVar('Files', 'allowedExtensions', 'gif,png,jpg,odt,doc,pdf,zip');
        ModUtil::setVar('Files', 'defaultQuota', 1);
        ModUtil::setVar('Files', 'groupsQuota', 's:0:"";');
        ModUtil::setVar('Files', 'filesMaxSize', '1000000');
        ModUtil::setVar('Files', 'maxWidth', '250');
        ModUtil::setVar('Files', 'maxHeight', '250');
        ModUtil::setVar('Files', 'editableExtensions', 'php,htm,html,htaccess,css,js,tpl');
        // Set up module hook
        ModUtil::registerHook('item', 'display', 'GUI', 'Files', 'user', 'Files');
        return true;
    }
}