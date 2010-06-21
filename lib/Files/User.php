<?php
/**
 * Zikula Application Framework
 *
 * @copyright  (c) Zikula Development Team
 * @link       http://www.zikula.org
 * @version    $Id: pnuser.php 202 2009-12-09 20:28:11Z aperezm $
 * @license    GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @author     Albert Pérez Monfort <aperezm@xtec.cat>
 * @category   Zikula_Extension
 * @package    Utilities
 * @subpackage Files
 */

class Files_User extends Zikula_Controller
{
    // set content of the files .htaccess and .locked
    protected $_HTACCESSCONTENT = "# Avoid direct web access to folder files\r\nOrder deny,allow\r\nDeny from all\r\n";
    protected $_LOCKEDCONTENT = "# Avoid direct web access with the file file.php\r\n";

    /**
     * List the files in server folder
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	args   the folder name where to list the files and subfolders
     * @return:	The list of files and folders
     */
    public function main($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        // get arguments
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'REQUEST');
        // security check
        if (!SecurityUtil::checkPermission('Files::', '::', ACCESS_ADD) || !UserUtil::login()) {
            return LogUtil::registerPermissionError();
        }
        $oFolder = $folder;
        // gets root folder for the user
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // check if the root folder exists
        if (!file_exists($initFolderPath)) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('The server directory does not exist.', $dom);
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        // get folder name
        $folderName = str_replace($initFolderPath . '/', '', $folder);
        $folder = $initFolderPath . '/' . $folder;
        // users can not browser the thumbnails folders
        if (strpos($folder, '.tbn') !== false) {
            LogUtil::registerError($this->__('It is not possible to browse this folder', $dom));
            return System::redirect(ModUtil::url('Files', 'user', 'main', array('folder' => substr($folderName, 0, strrpos($folderName, '/')))));
        }
        // needed arguments
        // check if the folder exists
        if (!file_exists($folder)) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folderName;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        // get user's disk use
        $userDiskUse = ModUtil::apiFunc('Files', 'user', 'get');
        $usedSpace = $userDiskUse['diskUse'];
        // get user's allowed space
        $userAllowedSpace = ModUtil::func('Files', 'user', 'getUserQuota');
        $maxDiskSpace = round($userAllowedSpace * 1024 * 1024);
        $percentage = ($maxDiskSpace == 0) ? 100 : round($usedSpace * 100 / $maxDiskSpace);
        $widthUsage = ($percentage > 100) ? 100 : $percentage;
        $usedSpaceArray = array('maxDiskSpace' => ModUtil::func('Files', 'user', 'diskUseFormat', array('value' => $maxDiskSpace)),
                                    'percentage' => $percentage,
                                    'usedDiskSpace' => ModUtil::func('Files', 'user', 'diskUseFormat', array('value' => $usedSpace)),
                                    'widthUsage' => $widthUsage);
        // create output object
        $renderer = Renderer::getInstance('Files', false);
        // get folder files and subfolders
        $fileList = $this->dir_list(array('folder' => $folder));                            
        sort($fileList[dir]);
        sort($fileList[file]);
        if (!is_writable($folder)) {
            $renderer->assign('notwriteable', true);
        }
        // check if it is a public directori
        if (!file_exists($folder . '/.locked')) {
            // it is a public directori
            $is_public = true;
        }
        $renderer->assign('publicFolder', $is_public);
        $renderer->assign('folderPrev', DataUtil::formatForDisplay(substr($folderName, 0, strrpos($folderName, '/'))));
        if (SecurityUtil::checkPermission('Files::', '::', ACCESS_ADMIN)) {
            $renderer->assign('folderPath', DataUtil::formatForDisplay($folderName));
        } else {
            $renderer->assign('folderPath', DataUtil::formatForDisplay(ModUtil::getVar('Files', 'usersFolder') . '/' . strtolower(substr(UserUtil::getVar('uname'), 0, 1)) . '/' . UserUtil::getVar('uname') . '/' . $folderName));
        }
        $renderer->assign('folderName', DataUtil::formatForDisplay($folderName));
        $renderer->assign('fileList', $fileList);
        $renderer->assign('usedSpace', $usedSpaceArray);
        return $renderer->fetch('Files_user_filesList.htm');
    }
    
    /**
     * format the use space
     * @author:	Albert Pérez Monfort
     * @param:	Disk use value
     * @return:	Value formated
     */
    public function diskUseFormat($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $value = FormUtil::getPassedValue('value', isset($args['value']) ? $args['value'] : null, 'POST');
    
        if ($value > 209715200) {
            $returnValue = $this->__('More than 200 Mb', $dom);
        } else if ($value >= 1048576) {
            $returnValue = __f("%s Mb", number_format(($value / 1048576), 2), $dom);
        } else if ($value >= 1024) {
            $returnValue = __f("%s Kb", number_format(($value / 1024), 2), $dom);
        } else {
            $returnValue = __f("%s bytes", $value, $dom);
        }
    
        return $returnValue;
    }
    
    /**
     * Create a .htaccess and .locked files in the folder to protect access
     * @author:	Albert Pérez Monfort
     * @param:	dir Folder Path
     * @return:	Objects array of the files
     */
    public function createaccessfile($args)
    {
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'POST');
        $folderNew = FormUtil::getPassedValue('folderNew', isset($args['folderNew']) ? $args['folderNew'] : null, 'POST');
        $onlyHtaccess = FormUtil::getPassedValue('onlyHtaccess', isset($args['onlyHtaccess']) ? $args['onlyHtaccess'] : null, 'POST');
    
        if ($folderNew != null) {
            $folder = $folderNew;
        }
        // security check
        if (!SecurityUtil::checkPermission('Files::', '::', ACCESS_ADD)) {
            return LogUtil::registerPermissionError();
        }
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $file = $initFolderPath . '/' . $folder . '/.htaccess';
        if (!file_exists($file)) {
            FileUtil::writeFile($file, $this->_HTACCESSCONTENT, true);
        }
        if ($onlyHtaccess != 1) {
            $file = $initFolderPath . '/' . $folder . '/.locked';
            if (!file_exists($file)) {
                FileUtil::writeFile($file, $this->_LOCKEDCONTENT, true);
            }
        }
        return true;
    }
    
    /**
     * List the information files in folder
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	dir Folder Path
     * @return:	Objects array of the files
     */
    public function dir_list($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'POST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'POST');
        // security check
        if (!SecurityUtil::checkPermission('Files::', '::', ACCESS_ADD)) {
            return LogUtil::registerPermissionError();
        }
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        // needed arguments
        // check if the directory of document root files exists
        //main logical functionality
        $folderName = str_replace($initFolderPath . '/', '', $folder);
        if (!file_exists($folder)) {
            return LogUtil::registerError($this->__('Invalid folder', $dom) . ': ' . $folderName);
        }
    
        // check is the last character is a /
        if ($dir[strlen($folder) - 1] != '/') {
            $folder .= '/';
        }
    
        // check is a directory
        if (!is_dir($folder)) {
            return array();
        }
    
        // set editable and thumbnailable extensions
        $editableExtensions = ModUtil::getVar('Files','editableExtensions');
        $thumbnailExtensions = array('gif','jpg','png');
        $dir_handle = opendir($folder);
        $dir_objects = array();
        while ($object = readdir($dir_handle)) {
            if (!in_array($object, array('.', '..'))) {
                $filename = DataUtil::formatForDisplay($folder . $object);
                // get file extension
                $fileExtension = FileUtil::getExtension($filename);
                // get file icon
                $ctypeArray = ModUtil::func('Files', 'user', 'getMimetype',
                                        array('extension' => $fileExtension));                                      
                $editable = (strpos($editableExtensions, strtolower($fileExtension)) !== false) ? 1 : 0;
                $thumbnailable = (in_array(strtolower($fileExtension), $thumbnailExtensions)) ? 1 : 0;
                $fileIcon = $ctypeArray['icon'];
                $options = array();
                if (substr($filename, strrpos($filename, '/') + 1, 1) != '.' || ModUtil::getVar('Files', 'showHideFiles') == 1 || (ModUtil::getVar('Files', 'showHideFiles') == 2 && SecurityUtil::checkPermission('Files::', '::', ACCESS_ADMIN))) {
                    if (strtolower($fileExtension) == 'zip') {
                        $options[] = array('url' => ModUtil::url('Files', 'user', 'action',
                                                            array('do' => 'unzip',
                                                            'fileName' => $object,
                                                            'folder' => $folderName,
                                                            'external' => $external)),
                                           'image' => 'folder_tar.gif',
                                           'title' => $this->__('Unzip file'));
                        $options[] = array('url' => ModUtil::url('Files', 'user', 'action',
                                                            array('do' => 'listcontentzip',
                                                            'fileName' => $object,
                                                            'folder' => $folderName,
                                                            'external' => $external)),
                                           'image' => 'list.gif',
                                           'title' => $this->__('List of the file content'));
                    }
                    $options[] = array('url' => ModUtil::url('Files', 'user', 'downloadFile',
                                                        array('fileName' => $object,
                                                        'folder' => $folderName)),
                                       'image' => 'agt_update_misc.gif',
                                       'title' => $this->__('Download file'));
                    if ($editable) {
                        $options[] = array('url' => ModUtil::url('Files', 'user', 'action',
                                                            array('do' => 'edit',
                                                            'fileName' => $object,
                                                            'folder' => $folderName,
                                                            'external' => $external)),
                                           'image' => 'xedit.gif',
                                           'title' => $this->__('Edit file'));
                    }
                    if ($thumbnailable && $external == 1 && !file_exists($folder . '.tbn/' . $object)) {
                        $options[] = array('url' => ModUtil::url('Files', 'user', 'action',
                                                            array('do' => 'thumbnail',
                                                            'fileName' => $object,
                                                            'folder' => $folderName,
                                                            'external' => $external)),
                                           'image' => 'inline_image.gif',
                                           'title' => $this->__('Create Thumbnail'));                    
                    }
                    $options[] = array('url' => ModUtil::url('Files', 'user', 'action',
                                                        array('do' => 'rename',
                                                        'fileName' => $object,
                                                        'folder' => $folderName,
                                                        'external' => $external)),
                                       'image' => 'edit.gif',
                                       'title' => $this->__('Rename file'));
                    $options[] = array('url' => ModUtil::url('Files', 'user', 'action',
                                                        array('do' => 'delete',
                                                        'fileName' => $object,
                                                        'folder' => $folderName,
                                                        'external' => $external)),
                                       'image' => '14_layer_deletelayer.gif',
                                       'title' => $this->__('Delete File'));
                    $file_object = array(
                        'name' => DataUtil::formatForDisplay($object),
                        'size' => filesize($filename),
                        'type' => filetype($filename),
                        'time' => date("j F Y, H:i", filemtime($filename)),
                        'fileIcon' => $fileIcon,
                        'options' => $options);
                    if (is_dir($filename)) {
                        $dir_objects[dir][] = $file_object;
                    } else {
                        $dir_objects[file][] = $file_object;
                    }
                }
            }
        }
        closedir($dir_handle);
        return $dir_objects;
    }
    
    /**
     * Downloa a file.
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	name of the file that is going to be downloaded and forder where this file is located
     * @return:	Download the requested file
     */
    public function downloadFile($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        // file name with the path
        $fileName = FormUtil::getPassedValue('fileName', isset($args['fileName']) ? $args['fileName'] : 0, 'GET');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : 0, 'GET');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : 0, 'GET');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        $file = ($folder != "") ? $folder . "/" . $fileName : $fileName;
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        // check if file exists. If not returns error.
        if (!file_exists($initFolderPath . '/' . $file)) {
            LogUtil::registerError($this->__('File not found', $dom));
            return System::redirect(ModUtil::url('Files', 'user', 'main', array('folder' => $folder, 'hook' => $hook)));
        }
        // get file size
        $fileSize = filesize($initFolderPath . '/' . $file);
        // get file extension
        $fileExtension = FileUtil::getExtension($fileName);
        $ctypeArray = ModUtil::func('Files', 'user', 'getMimetype',
                                 array('extension' => $fileExtension));
        $ctype = $ctypeArray['type'];
        // begin writing headers
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        // use the switch-generated Content-Type
        header("Content-Type: $ctype");
        // force the download
        $header = "Content-Disposition: attachment; filename=" . $fileName . ";";
        header($header);
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: " . $fileSize);
        @readfile($initFolderPath . '/' . $file);
        return true;
    }
    
    /**
     * Select an action.
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	action to do, folder where the action will be done and file name
     * @return:	Redirect user to the function that do the action
     */
    public function action($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $do = FormUtil::getPassedValue('do', isset($args['do']) ? $args['do'] : null, 'GET');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'GET');
        $fileName = FormUtil::getPassedValue('fileName', isset($args['fileName']) ? $args['fileName'] : null, 'GET');
        $thumb = FormUtil::getPassedValue('thumb', isset($args['thumb']) ? $args['thumb'] : 0, 'GET');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'GET');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        switch ($do) {
            case 'delete':
                return ModUtil::func('Files', 'user', 'deleteListFile', array(
                    'folder' => $folder,
                    'fileName' => $fileName,
                    'thumb' => $thumb,
                    'hook' => $hook));
                    break;
            case 'rename':
                return ModUtil::func('Files', 'user', 'renameFile', array(
                    'folder' => $folder,
                    'fileName' => $fileName,
                    'hook' => $hook));
                break;
            case 'unzip':
                return ModUtil::func('Files', 'user', 'unzipFile', array(
                    'folder' => $folder,
                    'fileName' => $fileName,
                    'hook' => $hook));
                break;
            case 'listcontentzip':
                return ModUtil::func('Files', 'user', 'listContentZip', array(
                    'folder' => $folder,
                    'fileName' => $fileName,
                    'hook' => $hook));
                break;
            /*case 'upload':
                return ModUtil::func('Files', 'user', 'uploadFile', array(
                    'folder' => $folder));
                break;
            case 'createDir':
                return ModUtil::func('Files', 'user', 'createDir', array(
                    'folder' => $folder));
                break;
            case 'createaccessfile':
                return ModUtil::func('Files', 'user', 'createaccessfile', array(
                    'folder' => $folder));
                break;*/
            case 'edit':
                return ModUtil::func('Files', 'user', 'editFile', array(
                    'folder' => $folder,
                    'fileName' => $fileName));
                break;
            case 'thumbnail':
                return ModUtil::func('Files', 'user', 'thumbnail', array(
                    'folder' => $folder,
                    'fileName' => $fileName,
                    'hook' => $hook));
                break;
        }
        return System::redirect(ModUtil::url('Files', 'user', 'main'));
    }
    
    /**
     * Create a thumbnail from a image
     * @author:	Albert Pérez Monfort
     * @param:	name of the file that is going to be downloaded, forder where this file is located and width of the thumbnail
     * @return:	true if success and false otherwise
     */
    public function thumbnail($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $fileName = FormUtil::getPassedValue('fileName', isset($args['fileName']) ? $args['fileName'] : null, 'POST');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'POST');
        $newWidth = FormUtil::getPassedValue('newWidth', isset($args['newWidth']) ? $args['newWidth'] : null, 'POST');
        $fromAjax = FormUtil::getPassedValue('fromAjax', isset($args['fromAjax']) ? $args['fromAjax'] : null, 'POST');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'POST');
    
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
    
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
    
        // create thumbnail image
        $fileExtension = FileUtil::getExtension($fileName);
        $thumbnailExtensions = array('gif','jpg','png');
        if (!in_array($fileExtension,$thumbnailExtensions)) {
            LogUtil::registerError($this->__('It is not possible to create a thumbnail from this file.', $dom));
            return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));
        }
        // checks if the thumbnail folder exists and if it not exists create it
        if (!file_exists($initFolderPath . '/' . $folder . '/.tbn')) {
            FileUtil::mkdirs($initFolderPath . '/' . $folder . '/.tbn', 0777, true);
        }
        // set origen a final destinations of the file
        $imgSource = $initFolderPath . '/' . $folder . '/' . $fileName;
        $imgDest = $initFolderPath . '/' . $folder . '/.tbn/' . $fileName;
        // check if file exists
        if (!file_exists($imgSource)) {
            LogUtil::registerError($this->__('Error! It has been possible to read the file.', $dom));
            return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));        
        }
        $format = '';
    	if (strtolower($fileExtension) == 'jpg') {
    		$format = 'image/jpeg';
    	} elseif (strtolower($fileExtension) == 'gif') {
    		$format = 'image/gif';
    	} elseif (strtolower($fileExtension) == 'png') {
    		$format = 'image/png';
    	}
    
        // size calculation
    	// get original image size
    	list($width, $height) = getimagesize($imgSource);
    	// fix the width to the value set in the module configuration (or lower if the image is smaller) and calc the height
        if ($newWidth == null) {
            $newWidth = ($width <= ModUtil::getVar('Files', 'maxWidth')) ? $width : ModUtil::getVar('Files', 'maxWidth');
        	$newHeight = $height * $newWidth / $width;
            if ($newHeight > ModUtil::getVar('Files', 'maxHeight')) {
                $newHeight = ModUtil::getVar('Files', 'maxHeight');
                $newWidth = $width * $newHeight / $height;
            }
        } else {
            $newHeight = $height * $newWidth / $width;
        }
    
    	if (!$destimg = imagecreatetruecolor($newWidth, $newHeight)) {
            LogUtil::registerError($this->__('Error! Saving the image in memory.', $dom));
            return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook))); 
    	}
        // set alphablending to on
        imagesavealpha($destimg, true);
        imagealphablending($destimg, true);
    	// create the image
    	switch($format) {
    		case 'image/gif':
    			if (!$srcimg = imagecreatefromgif($imgSource)) {
                    LogUtil::registerError($this->__('Error! Creating the thumbnail of the image.', $dom));
                    return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));
    			}
                // preserve the transparency
                $transIndex = imagecolortransparent($srcimg);
                if ($transIndex >= 0) {
                    // get transparent colors from the received image
                    $transColor    = imagecolorsforindex($srcimg, $transIndex);
                    // allocate the color to the destiny image
                    $transIndex    = imagecolorallocate($destimg, $transColor['red'], $transColor['green'], $transColor['blue']);
                    // fills the background of destiny image with the allocated color.
                    imagefill($destimg, 0, 0, $transIndex);
                    // set the background color for destiny image to transparent
                    imagecolortransparent($destimg, $transIndex);
                }
    			if (!imagecopyresampled($destimg,$srcimg,0,0,0,0,$newWidth,$newHeight,imagesx($srcimg),imagesy($srcimg))) {
                    LogUtil::registerError($this->__('Error! Creating the thumbnail of the image.', $dom));
                    return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));
    			}
    		    if (!imagegif($destimg,$imgDest)) {
                    LogUtil::registerError($this->__('Error! Creating the thumbnail of the image.', $dom));
                    return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));
    		    }
    			break;
    		case 'image/jpeg':
    			if (!$srcimg = imagecreatefromjpeg($imgSource)) {
                    LogUtil::registerError($this->__('Error! Creating the thumbnail of the image.', $dom));
                    return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));
    			}
    			if (!imagecopyresampled($destimg,$srcimg,0,0,0,0,$newWidth,$newHeight,ImageSX($srcimg),ImageSY($srcimg))) {
                    LogUtil::registerError($this->__('Error! Creating the thumbnail of the image.', $dom));
                    return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));
    			}
    		    if (!imagejpeg($destimg,$imgDest)) {
                    LogUtil::registerError($this->__('Error! Creating the thumbnail of the image.', $dom));
                    return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));
    		    }
    			break;
    		case 'image/png':
    			if (!$srcimg = imagecreatefrompng($imgSource)) {
                    LogUtil::registerError($this->__('Error! Creating the thumbnail of the image.', $dom));
                    return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));
    			}
                // preserve the transparency
                // turns off transparency blending
                imagealphablending($destimg, false);
                // create a transparent color
                $color = imagecolorallocatealpha($destimg, 0, 0, 0, 127);
                // fills the background of the image with the allocated color.
                imagefill($destimg, 0, 0, $color);
                // turns on transparency blending
                imagesavealpha($destimg, true);
    			if (!imagecopyresampled($destimg,$srcimg,0,0,0,0,$newWidth,$newHeight,ImageSX($srcimg),ImageSY($srcimg))) {
                    LogUtil::registerError($this->__('Error! Creating the thumbnail of the image.', $dom));
                    return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));
    			}
    		  	if (!imagepng($destimg,$imgDest)) {
                    LogUtil::registerError($this->__('Error! Creating the thumbnail of the image.', $dom));
                    return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));
    		  	}
    			break;
    	}
        // frees image from memory
        imagedestroy($destimg);
        // only if the image is not resizing via Ajax
        if ($fromAjax == null) {
            LogUtil::registerStatus($this->__('Thumbnail created.', $dom));
        }
        return System::redirect(ModUtil::url('Files', 'external', 'getFiles', array('folder' => $folder, 'hook' => $hook)));
    }
    
    /**
     * Edit a file.
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	folder where the action have begined, file that must be edited, confirmation parameter
     * @return:	True if success and false if not
     */
    public function editFile($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $fileName = FormUtil::getPassedValue('fileName', isset($args['fileName']) ? $args['fileName'] : null, 'POST');
        $fileContent = FormUtil::getPassedValue('fileContent', isset($args['fileContent']) ? $args['fileContent'] : null, 'POST');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'REQUEST');
        $confirm = FormUtil::getPassedValue('confirm', isset($args['confirm']) ? $args['confirm'] : null, 'POST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'GETPOST');
    
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
    
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
    
        //checks if it is an editable file
        // set editable extensions
        $editableExtensions = ModUtil::getVar('Files', 'editableExtensions');
        // get file extension
        $fileExtension = FileUtil::getExtension($fileName);
        if (strpos($editableExtensions, strtolower($fileExtension)) === false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = __f('Sorry! The file %s is not editable.', $fileName, $dom);
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
    
        // checks if file exists
        $file = $initFolderPath . '/' . $folder . '/' . $fileName;
        if (!file_exists($file)) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = __f('Sorry! The file %s has not been found.', $fileName, $dom);
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
    
        if (!$confirm) {
            // load the edit form
            // get file content
            if (!$fileContent = FileUtil::readFile($file, true)) {
                // error reading the file
                $renderer = Renderer::getInstance('Files', false);
                $errorMsg = __f('Error! It has not been possible to read the content of the file %s.', $fileName, $dom);
                $renderer->assign('errorMsg', $errorMsg);
                return $renderer->fetch('Files_user_errorMsg.htm');
            }
            // create output object
            $renderer = Renderer::getInstance('Files', false);
            $renderer->assign('folder', DataUtil::formatForDisplay($folder));
            $renderer->assign('fileName', DataUtil::formatForDisplay($fileName));
            $renderer->assign('fileContent', DataUtil::formatForDisplay($fileContent));
            if ($external == 1) {
                $renderer->assign('external', 1);
                $content = $renderer->fetch('Files_user_editFile.htm');
                echo $content;
                exit();
            } else {
                return $renderer->fetch('Files_user_editFile.htm');
            }
        }
        $returnType = ($external == 1) ? 'external' : 'user';
        $returnFunc = ($external == 1) ? 'getFiles' : 'main';
    
        // confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Files', $returnType, $returnFunc));
        }
    
        // the file has been edited. Update its content
    	if (!FileUtil::writeFile($file, $fileContent, true)) {
            // error writing the file
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = __f('Error! It has not been possible to write the content to the file %s.', $fileName, $dom);
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');	    
    	}
    
        // update the number of bytes used by user
        ModUtil::apiFunc('Files', 'user', 'updateUsedSpace');
        LogUtil::registerStatus($this->__('File successfully edited', $dom));
        return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder)));
    }
    
    /**
     * Deletes a list of files and directories.
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	folder where the action have begined, file that must be deleted or list of files, confirmation parameter
     * @return:	True if success and false if not
     */
    public function deleteListFile($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $fileName = FormUtil::getPassedValue('fileName', isset($args['fileName']) ? $args['fileName'] : null, 'POST');
        $listFileName = FormUtil::getPassedValue('listFileName', isset($args['listFileName']) ? $args['listFileName'] : null, 'GETPOST');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'GETPOST');
        $confirm = FormUtil::getPassedValue('confirm', isset($args['confirm']) ? $args['confirm'] : null, 'POST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'GETPOST');
        $thumb = FormUtil::getPassedValue('thumb', isset($args['thumb']) ? $args['thumb'] : null, 'GETPOST');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'GET');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        // add fileName in a Array with file list
        if (isset($fileName)) {
            $listFileName[] = $fileName;
        }
        if ($fileName == ModUtil::getVar('Files', 'usersFolder')) {
            LogUtil::registerError($this->__('You can not delete the users folder!', $dom));
            $returnType = ($external == 1) ? 'external' : 'user';
            $returnFunc = ($external == 1) ? 'getFiles' : 'main';
            return System::redirect(ModUtil::url('Files', $returnType, $returnFunc,
                                        array('folder' => $folder, 'hook' => $hook)));
        }
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        if (!$confirm) {
            $array_items = array();
            foreach ($listFileName as $file) {
                $filePath = ($folder != "") ? $initFolderPath . "/" . $folder . "/" . $file : $initFolderPath . "/" . $file;
                $array_items[] = $filePath;
                if (is_dir($filePath)) {
                    $array_items = array_merge($array_items, ModUtil::func('Files', 'user', 'getListRecursive', array('dir' => $filePath)));
                }
            }
            $array_show = array();
            foreach ($array_items as $item) {
                $array_show[] = str_replace($initFolderPath . "/", "", $item);
            }
            // create output object
            $renderer = Renderer::getInstance('Files', false);
            $renderer->assign('listFileName', DataUtil::formatForDisplay($listFileName));
            $renderer->assign('list_show', DataUtil::formatForDisplay($array_show));
            $renderer->assign('folder', DataUtil::formatForDisplay($folder));
            $renderer->assign('thumb', $thumb);
            $renderer->assign('hook', $hook);
            if ($external == 1) {
                $renderer->assign('external', 1);
                $content = $renderer->fetch('Files_user_deleteListFile.htm');
                echo $content;
                exit();
            } else {
                return $renderer->fetch('Files_user_deleteListFile.htm');
            }
        }
        $returnType = ($external == 1) ? 'external' : 'user';
        $returnFunc = ($external == 1) ? 'getFiles' : 'main';
        // confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Files', $returnType, $returnFunc));
        }
        foreach ($listFileName as $fileName) {
            if ($thumb == 1) {
                $file = ($folder != "") ? $initFolderPath . "/" . $folder . "/.tbn/" . $fileName : $initFolderPath . "/.tbn/" . $fileName;
            } else {
                $file = ($folder != "") ? $initFolderPath . "/" . $folder . "/" . $fileName : $initFolderPath . "/" . $fileName;
            }
            if (is_dir($file)) {
                if (!ModUtil::func('Files', 'user', 'fullDeleteDir', array(
                    'file' => $file))) {
                    LogUtil::registerError($this->__('Failed to deleted', $dom));
                    return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
                }
            } else {
                if (!unlink($file)) {
                    LogUtil::registerError($this->__('Error deleting file') . ': ' . $fileName);
                } else {
                    $fileThumb = ($folder != "") ? $initFolderPath . "/" . $folder . "/.tbn/" . $fileName : $initFolderPath . "/.tbn/" . $fileName;
                    if (file_exists($fileThumb)) {
                        unlink($fileThumb);
                    }
                }
            }
        }
        // update the number of bytes used by user
        ModUtil::apiFunc('Files', 'user', 'updateUsedSpace');
        LogUtil::registerStatus($this->__('Files successfully removed', $dom));
        return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
    }
    
    /**
     * List the directories in server folder
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	the directory name where it is request the list of directories and subdirectories
     * @return:	Array with the list of directories and subdirectories
     */
    public function getListDirRecursive($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $dir = FormUtil::getPassedValue('dir', isset($args['dir']) ? $args['dir'] : null, 'REQUEST');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($dir == ".." || $dir == "." || strpos($dir, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $array_items = array();
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    if (is_dir($dir . "/" . $file) && (substr($file, strrpos($file, '/') + 0, 1) != '.' || ModUtil::getVar('Files', 'showHideFiles') == 1 || (ModUtil::getVar('Files', 'showHideFiles') == 2 && SecurityUtil::checkPermission('Files::', '::', ACCESS_ADMIN)))) {
                            $array_items[] = str_replace($initFolderPath . "/", "", $dir . "/" . $file);
                            $file = $dir . "/" . $file;
                            $array_items = array_merge($array_items, ModUtil::func('Files', 'user', 'getListDirRecursive',
                                                                                array('dir' => $file)));
                    }
                }
            }
            closedir($handle);
        }
        return $array_items;
    }
    
    /**
     * List the directories and files in server folder
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	args   Array with the folder name where list the files , directories and subdirectories
     * @return:	Array with the list of files, directories and subdirectories
     */
    public function getListRecursive($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $dir = FormUtil::getPassedValue('dir', isset($args['dir']) ? $args['dir'] : null, 'REQUEST');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($dir == ".." || $dir == "." || strpos($dir, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $array_items = array();
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != ".." ) {
                    if (substr($file, strrpos($file, '/') + 0, 1) != '.' || ModUtil::getVar('Files', 'showHideFiles') == 1 || (ModUtil::getVar('Files', 'showHideFiles') == 2 && SecurityUtil::checkPermission('Files::', '::', ACCESS_ADMIN))) {
                        $array_items[] = str_replace($initFolderPath . "/", "", $dir . "/" . $file);
                        if (is_dir($dir . "/" . $file)) {
                            $file = $dir . "/" . $file;
                            $array_items = array_merge($array_items, ModUtil::func('Files', 'user', 'getListRecursive', array('dir' => $file)));
                        }
                    }
                }
            }
            closedir($handle);
        }
        return $array_items;
    }
    
    /**
     * Renames a file or directory.
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	Name of the file, folder where the action is made, confirmation parameter from the form and new name
     * @return:	True if success and false if not
     */
    public function renameFile($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $fileName = FormUtil::getPassedValue('fileName', isset($args['fileName']) ? $args['fileName'] : null, 'REQUEST');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'REQUEST');
        $confirm = FormUtil::getPassedValue('confirm', isset($args['confirm']) ? $args['confirm'] : null, 'POST');
        $newName = FormUtil::getPassedValue('newname', isset($args['newname']) ? $args['newname'] : null, 'POST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'GETPOST');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'GET');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $file = ($folder != "") ? $initFolderPath . "/" . $folder . "/" . $fileName : $initFolderPath . "/" . $fileName;
        // needed arguments
        // check if the directory of document root files exists
        if (!file_exists($file)) {
            LogUtil::registerError($this->__('File not found') . ": " . $fileName);
            return System::redirect(ModUtil::url('Files', 'user', 'main'));
        }
        if (!$confirm) {
            // show an error page
            // create output object
            $renderer = Renderer::getInstance('Files', false);
            $renderer->assign('fileName', DataUtil::formatForDisplay($fileName));
            $renderer->assign('folder', DataUtil::formatForDisplay($folder));
            if ($external == 1) {
                $renderer->assign('external', 1);
                $renderer->assign('hook', $hook);
                $content = $renderer->fetch('Files_user_renameFile.htm');
                echo $content;
                exit();
            } else {
                return $renderer->fetch('Files_user_renameFile.htm');
            }
        }
        $returnType = ($external == 1) ? 'external' : 'user';
        $returnFunc = ($external == 1) ? 'getFiles' : 'main';
        // confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Files', $returnType, $returnFunc));
        }
        // check if exists a file with the same name in the folder -> Inactive
        /*if (($fileName != $newName)&&(file_exists($initFolderPath . "/" . $folder . "/" . $newName))){
            LogUtil::registerError(__f('Failed to rename, there\'s a file with the same name in this folder.<br>Please, choose another name' , $dom));
                return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder)));
        }*/ 
        
        // check if the extension is allowed
        // get file extension
        $file_extension = FileUtil::getExtension($newName);
        if ($file_extension != '') {
            $allowedExtensions = ModUtil::getVar('Files', 'allowedExtensions');
            $allowedExtensionsArray = explode(',', $allowedExtensions);
            if (!in_array(strtolower($file_extension), $allowedExtensionsArray) && !in_array(strtoupper(($file_extension)), $allowedExtensionsArray)) {
                LogUtil::registerError(__f('The extension <strong>%s</strong> is not allowed.  The allowed extensions are: <strong>%s</strong>.', array(
                    $file_extension,
                    str_replace(',', ', ', $allowedExtensions)), $dom));
                return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook'=> $hook)));
            }
        }
        $newNameFile = ($folder != "") ? $initFolderPath . "/" . $folder . "/" . $newName : $initFolderPath . "/" . $newName;
        if (!rename($file, $newNameFile)) {
            LogUtil::registerError($this->__('Failed to rename', $dom));
            return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('hook'=> $hook)));
        } else {
            $file = ($folder != "") ? $initFolderPath . "/" . $folder . "/.tbn/" . $fileName : $initFolderPath . "/.tbn/" . $fileName;
            if (file_exists($file)) {
                // get file extension
                $file_extension = FileUtil::getExtension($fileName);
                $new_file_extension = FileUtil::getExtension($newName);
                // if the extension is different between the original and the final file the file is deleted otherwise the file is renamed
                if (strtolower($file_extension) != strtolower($new_file_extension)) {
                    unlink($file);
                } else {
                    $newNameFile = ($folder != "") ? $initFolderPath . "/" . $folder . "/.tbn/" . $newName : $initFolderPath . "/.tbn/" . $newName;
                    rename($file, $newNameFile);
                }
            }
        }
        LogUtil::registerStatus($this->__('Changed filename successfully', $dom));
        return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook'=> $hook)));
    }
    
    /**
     * Create a new directory
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	folder where the directory must be created
     * @param:	confirmation value
     * @param:	Name of the new folder
     * @return:	True if success and false if not
     */
    public function createDir($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : 0, 'REQUEST');
        $confirm = FormUtil::getPassedValue('confirm', isset($args['confirm']) ? $args['confirm'] : null, 'POST');
        $newFolder = FormUtil::getPassedValue('newFolder', isset($args['newFolder']) ? $args['newFolder'] : 0, 'POST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'POST');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'POST');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        // confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Files', 'user', 'main'));
        }
        
        $returnType = ($external == 1) ? 'external' : 'user';
        $returnFunc = ($external == 1) ? 'getFiles' : 'main';
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            $renderer->assign('hook', $hook);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $folderNew = ($folder != "") ? $initFolderPath . "/" . $folder . "/" . $newFolder : $initFolderPath . "/" . $newFolder;
        if (!FileUtil::mkdirs($folderNew, 0777, true)) {
            LogUtil::registerError($this->__('Directory Create Error') . ': ' . $folder . "/" . $newFolder);
            return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
        }
        LogUtil::registerStatus($this->__('Directory created', $dom));
        $inFolder = ($folder == '') ? $newFolder : $folder . "/" . $newFolder;
        ModUtil::func('Files', 'user', 'createaccessfile', array('folderNew' => $inFolder));
        // update the number of bytes used by user
        ModUtil::apiFunc('Files', 'user', 'updateUsedSpace');
        return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
    }
    
    /**
     * Show the form needed to create a directory
     * @author:	Albert Pérez Monfort
     * @param:	folder name
     * @return:	The form content
     */
    public function createDirForm($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : 0, 'REQUEST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'GET');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'GET');
        // Security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $type = ($external == 1) ? 'external' : 'user';
        $func = ($external == 1) ? 'getFiles' : 'main';
        // Create output object
        $renderer = Renderer::getInstance('Files', false);
        $renderer->assign('folder', DataUtil::formatForDisplay($folder));
        $renderer->assign('type', $type);
        $renderer->assign('func', $func);
        $renderer->assign('external', $external);
        $renderer->assign('hook', $hook);
        return $renderer->fetch('Files_user_createDir.htm');
    }
    
    /**
     * Upload a file.
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	folder name, confirmation parameter from the form and new file name
     * @return:	True if success and false if not
     */
    public function uploadFile($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : 0, 'REQUEST');
        $confirm = FormUtil::getPassedValue('confirm', isset($args['confirm']) ? $args['confirm'] : null, 'POST');
        $newFile = FormUtil::getPassedValue('newFile', isset($args['newFile']) ? $args['newFile'] : 0, 'REQUEST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'POST');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'POST');
        // Security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $returnType = ($external == 1) ? 'external' : 'user';
        $returnFunc = ($external == 1) ? 'getFiles' : 'main';
        // Confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Files', $returnType, $returnFunc));
        }
        // gets the attached file array
        $fileName = $_FILES['newFile']['name'];
        // update the attached file to the server
        if ($fileName != '') {
            $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
            // check if folder exists. If not returns error.
            if (!file_exists($initFolderPath . '/' . $folder)) {
                LogUtil::registerError(__f('The directory <strong>%s</strong> does not exist', DataUtil::formatForDisplay($folder), $dom));
                return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
            }
            // get file extension
            $file_extension = FileUtil::getExtension($fileName);
            $allowedExtensions = ModUtil::getVar('Files', 'allowedExtensions');
            $allowedExtensionsArray = explode(',', $allowedExtensions);
            if (!in_array(strtolower($file_extension), $allowedExtensionsArray) && !in_array(strtoupper(($file_extension)), $allowedExtensionsArray)) {
                LogUtil::registerError(__f('The extension <strong>%s</strong> is not allowed. The allowed extensions are: <strong>%s</strong>.', array(
                    $file_extension,
                    str_replace(',', ', ', $allowedExtensions)), $dom));
                return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
            }
            // gets the file size
            $fileSize = $_FILES['newFile']['size'];
            // check the file
            if ($fileSize > ModUtil::getVar('Files', 'filesMaxSize')) {
                LogUtil::registerError(__f('The file is to big. The maximum size allowed is "%s" bytes.', ModUtil::getVar('Files', 'filesMaxSize'), $dom));
                return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array(
                    'folder' => $folder, 
                    'hook' => $hook)));
            }
            // get user used space in folder
            $userDiskUse = ModUtil::apiFunc('Files', 'user', 'get');
            $usedSpace = $userDiskUse['diskUse'];
            // get user allowed space
            $userAllowedSpace = ModUtil::func('Files', 'user', 'getUserQuota') * 1024 * 1024;
            // check if user have enough space to upload the file
            if ($fileSize + $usedSpace > $userAllowedSpace && $userAllowedSpace != -1048576) {
                LogUtil::registerError(__f('You have not enough disk space to upload the file.', $fileSize + $usedSpace - $userAllowedSpace, $dom));
                return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array(
                    'folder' => $folder, 
                    'hook' => $hook)));
            }
            // prepare file name
            if (!isset($fileNameNew) || $fileNameNew == '') {
                // replace spaces with _
                // check if file name exists into the folder. In this case change the name
                $fileNameNew = str_replace(' ', '_', $_FILES['newFile']['name']);
                $fitxer = $fileNameNew;
                $i = 1;
                while (file_exists($initFolderPath . '/' . $folder . '/' . $fileNameNew)) {
                    $fileNameNew = substr($fitxer, 0, strlen($fitxer) - strlen($file_extension) - (1)) . $i . '.' . $file_extension;
                    $i++;
                }
            }
            // update the file
            if (!FileUtil::uploadFile('newFile', $initFolderPath . '/' . $folder, $fileNameNew, true)) {
                LogUtil::registerError($this->__('Failed to upload', $dom));
                return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array(
                    'folder' => $folder, 
                    'hook' => $hook)));
            }
            LogUtil::registerStatus($this->__('Uploaded file success', $dom));
            // update the number of bytes used by user
            ModUtil::apiFunc('Files', 'user', 'updateUsedSpace');
        }
        return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
    }
    
    /**
     * Show the form needed to create a directory
     * @author:	Albert Pérez Monfort
     * @param:	folder name
     * @return:	The form content
     */
    public function uploadFileForm($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : 0, 'REQUEST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'GET');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'GET');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
    
        $type = ($external == 1) ? 'external' : 'user';
        $func = ($external == 1) ? 'getFiles' : 'main';
        // create output object
        $renderer = Renderer::getInstance('Files', false);
        $renderer->assign('folder', DataUtil::formatForDisplay($folder));
        $renderer->assign('extensions', DataUtil::formatForDisplay(str_replace(',', ', ', ModUtil::getVar('Files', 'allowedExtensions'))));
        $renderer->assign('external', $external);
        $renderer->assign('hook', $hook);
        $renderer->assign('type', $type);
        $renderer->assign('func', $func);
        return $renderer->fetch('Files_user_uploadFile.htm');
    }
    
    /**
     * Select an action.
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	folder name and action to do
     * @return:	Redirect user to the function that do the action requested
     */
    public function actionSelect($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'GET');
        $menuaction = FormUtil::getPassedValue('menuaction', isset($args['menuaction']) ? $args['menuaction'] : null, 'POST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'POST');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'GET');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $returnType = ($external == 1) ? 'external' : 'user';
        $returnFunc = ($external == 1) ? 'getFiles' : 'main';
        $keyArray = array();
        foreach ($_REQUEST as $key => $value) {
            if ($value == 'on') {
                $key = str_replace("$$$$$", ".", $key);
                array_push($keyArray, str_replace("list_", "", $key));
            }
        }
        // needed arguments
        // check if the directory of document root files exists
        if ($menuaction == null) {
            LogUtil::registerError($this->__('No selected action', $dom));
            return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array(
                'folder' => $folder,
                'hook' => $hook)));
        }
        if (count($keyArray) == 0) {
            LogUtil::registerError($this->__('No selected file or directory', $dom));
            return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array(
                'folder' => $folder,
                'hook' => $hook)));
        }
        switch ($menuaction) {
            case move:
                return ModUtil::func('Files', 'user', 'moveListFile', array(
                    'listFileName' => $keyArray,
                    'external' => $external,
                    'folder' => $folder,
                	'hook' => $hook));
                break;
            case delete:
                return ModUtil::func('Files', 'user', 'deleteListFile', array(
                    'listFileName' => $keyArray,
                    'external' => $external,
                    'folder' => $folder,
                	'hook' => $hook));
                break;
            case zip:
                return ModUtil::func('Files', 'user', 'createZipListFile', array(
                    'listFileName' => $keyArray,
                    'external' => $external,
                    'folder' => $folder,
                	'hook' => $hook));
                break;
        }
        return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder)));
    }
    
    /**
     * Deletes a list of files and directories recursive.
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	file name or folder name that must to be deleted
     * @return:	True if success and false if not
     */
    public function fullDeleteDir($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $file = FormUtil::getPassedValue('file', isset($args['file']) ? $args['file'] : null, 'REQUEST');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        if ($handle = opendir($file)) {
            while (false !== ($item = readdir($handle))) {
                if ($item != '.' && $item != '..') {
                    // check if it is a directory or a file
                    if (is_dir($file . '/' . $item)) {
                        ModUtil::func('Files', 'user', 'fullDeleteDir', array(
                            'file' => $file . '/' . $item));
                        rmdir($file . '/' . $item);
                    } else {
                        // if it is not a directory we delete it
                        unlink($file . '/' . $item);
                    }
                }
            }
            closedir($handle);
        }
        if (rmdir($file)) {
            return true;
        }
        return false;
    }
    
    /**
     * Create file zip with files and directories
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	args
     * @return:	True if success and false if not
     */
    public function createZipListFile($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $listFileName = FormUtil::getPassedValue('listFileName', isset($args['listFileName']) ? $args['listFileName'] : null, 'REQUEST');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'REQUEST');
        $confirm = FormUtil::getPassedValue('confirm', isset($args['confirm']) ? $args['confirm'] : null, 'POST');
        $name = FormUtil::getPassedValue('name', isset($args['name']) ? $args['name'] : null, 'POST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'POST');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'POST');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        if (!$confirm) {
            $array_items = array();
            foreach ($listFileName as $file) {
                $filePath = ($folder != "") ? $initFolderPath . "/" . $folder . "/" . $file : $initFolderPath . "/" . $file;
                $array_items[] = $filePath;
                if (is_dir($filePath)) {
                    $array_items = array_merge($array_items, ModUtil::func('Files', 'user', 'getListRecursive', array(
                        'dir' => $filePath)));
                }
            }
            $array_show = array();
            foreach ($array_items as $item) {
                $array_show[] = str_replace($initFolderPath . "/", "", $item);
            }
            // create output object
            $renderer = Renderer::getInstance('Files', false);
            $renderer->assign('listFileName', DataUtil::formatForDisplay($array_show));
            $renderer->assign('folder', DataUtil::formatForDisplay($folder));
            if ($external == 1) {
                $renderer->assign('external', 1);
                $renderer->assign('hook', $hook);
                $content = $renderer->fetch('Files_user_createZipListFile.htm');
                echo $content;
                exit();
            } else {
                return $renderer->fetch('Files_user_createZipListFile.htm');
            }
        }
        $returnType = ($external == 1) ? 'external' : 'user';
        $returnFunc = ($external == 1) ? 'getFiles' : 'main';
        // confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Files', $returnType, $returnFunc));
        }
        if (name == null) {
            LogUtil::registerError($this->__('No file name given', $dom));
            return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
        }
        require_once ('includes/pclzip.lib.php');
        $zipFileName = ($folder != "") ? $initFolderPath . "/" . $folder . "/" . $name . ".zip" : $initFolderPath . "/" . $name . ".zip";
        $listZipFile = array();
        foreach ($listFileName as $file) {
            $filePath = $initFolderPath . "/" . $file;
            $listZipFile[] = $filePath;
        }
        $archive = new PclZip($zipFileName);
        if (!$archive->create(implode(',', $listZipFile), PCLZIP_OPT_REMOVE_PATH, ($initFolderPath . "/" . $folder))) {
            LogUtil::registerError($this->__('Error creating ZIP file') . ': ' . $file);
            return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
        }
        LogUtil::registerStatus($this->__('Successfully zipped', $dom));
        // update the number of bytes used by user
        ModUtil::apiFunc('Files', 'user', 'updateUsedSpace');
        return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
    }
    
    /**
     * Unzip a zip file.
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	args
     * @return:	True if success and false if not
     */
    public function listContentZip($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $fileName = FormUtil::getPassedValue('fileName', isset($args['fileName']) ? $args['fileName'] : null, 'GET');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'GET');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'GET');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'GET');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        $returnType = ($external == 1) ? 'external' : 'user';
        $returnFunc = ($external == 1) ? 'getFiles' : 'main';
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $file = ($folder != "") ? $initFolderPath . "/" . $folder . "/" . $fileName : $initFolderPath . "/" . $fileName;
        require_once ('includes/pclzip.lib.php');
        $archive = new PclZip($file);
        if (($list = $archive->listContent()) == 0) {
            LogUtil::registerError($this->__('Failed to list content zip file.', $dom) . ': ' . $fileName);
            return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
        }
        // create output object
        $renderer = Renderer::getInstance('Files', false);
        $renderer->assign('list', DataUtil::formatForDisplay($list));
        $renderer->assign('folder', DataUtil::formatForDisplay($folder));
        if ($external == 1) {
            $renderer->assign('external', 1);
            $renderer->assign('hook', $hook);
            $content = $renderer->fetch('Files_user_listContentZip.htm');
            echo $content;
            exit();
        } else {
            return $renderer->fetch('Files_user_listContentZip.htm');
        }
    }
    
    /**
     * Move a list file
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	args 	Array with the list of files and the folder where it generates
     * @return:	True if success and false if not
     */
    public function moveListFile($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $listFileName = FormUtil::getPassedValue('listFileName', isset($args['listFileName']) ? $args['listFileName'] : null, 'REQUEST');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'REQUEST');
        $confirm = FormUtil::getPassedValue('confirm', isset($args['confirm']) ? $args['confirm'] : null, 'POST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'POST');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'POST');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        if (!$confirm) {
            $url = $initFolderPath;
            $directoris = ModUtil::func('Files', 'user', 'getListDirRecursive', array('dir' => $url));
            foreach ($directoris as $dir) {
                foreach ($listFileName as $file) {
                    $file = ($folder != "") ? $folder . "/" . $file : $file;
                    if (is_dir($url . "/" . $file) && strpos($dir, $file) === 0) {
                        $array_dir[] = $dir;
                        $directoris = array_diff($directoris, $array_dir);
                    }
                }
            }
            // create output object
            $renderer = Renderer::getInstance('Files', false);
            $renderer->assign('listFileName', DataUtil::formatForDisplay($listFileName));
            $renderer->assign('directoris', DataUtil::formatForDisplay($directoris));
            $renderer->assign('folder', DataUtil::formatForDisplay($folder));
            if ($external == 1) {
                $renderer->assign('external', 1);
                $renderer->assign('hook', $hook);
                $content = $renderer->fetch('Files_user_moveListFile.htm');
                echo $content;
                exit();
            } else {
                return $renderer->fetch('Files_user_moveListFile.htm');
            }
        }
        $returnType = ($external == 1) ? 'external' : 'user';
        $returnFunc = ($external == 1) ? 'getFiles' : 'main';
        // confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Files', $returnType, $returnFunc, array(
                'folder' => $folder,
                'hook' => $hook)));
        }
        $url_old = ($folder != "") ? $initFolderPath . "/" . $folder . "/" : $initFolderPath . "/";
        $url_new = ($confirm != "root_inital_value") ? $initFolderPath . '/' . $confirm . '/' : $initFolderPath . '/';
        // move action
        foreach ($listFileName as $file) { 
            if (!rename($url_old . $file, $url_new . $file)) {
                LogUtil::registerError($this->__('Error moving') . ': ' . $file);
                return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder,'hook' => $hook)));
            }
            //check if the file is an image and move its thumbnail
            if((FileUtil::getExtension($file)==('jpg'||'gif'||'png'))&&file_exists($url_old . '.tbn/' . $file)){
                if(!file_exists($url_new . '.tbn')) mkdir($url_new . '.tbn'); 
                if (!rename($url_old . '.tbn/' . $file, $url_new . '.tbn/' . $file)) {
                    LogUtil::registerError($this->__('Error moving') . ': ' . $file);
                    return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder,'hook' => $hook)));
                }
            }
            
        }
        // protect the folders with the .htaccess and .locked files
        ModUtil::func('Files', 'user', 'createProtectFiles', array('folder' => str_replace($initFolderPath . '/', '', $url_new)));
        LogUtil::registerStatus($this->__('Successfully moved', $dom));
        return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder,'hook' => $hook)));
    }
    
    /**
     * Unzip a zip file.
     * @author:	Albert Pérez Monfort & Robert Barrera
     * @param:	args
     * @return:	True if success and false if not
     */
    public function unzipFile($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $fileName = FormUtil::getPassedValue('fileName', isset($args['fileName']) ? $args['fileName'] : null, 'REQUEST');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'REQUEST');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'GET');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'GET');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        $returnType = ($external == 1) ? 'external' : 'user';
        $returnFunc = ($external == 1) ? 'getFiles' : 'main';
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $file = ($folder != "") ? $initFolderPath . "/" . $folder . "/" . $fileName : $initFolderPath . "/" . $fileName;
        require_once ('includes/pclzip.lib.php');
        $archive = new PclZip($file);
        if (($list = $archive->listContent()) == 0) {
            LogUtil::registerError($this->__('Failed to list content zip file.', $dom) . ': ' . $fileName);
            return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
        }
        $filesSize = 0;
        $allowedExtensions = ModUtil::getVar('Files', 'allowedExtensions');
        $allowedExtensionsArray = explode(',', $allowedExtensions);
        foreach ($list as $file) {
            // calc the size of the file unziped
            $filesSize += $file['size'];
            // checks if user can unzip the file because the extensions into this file
            // get file extension
            $file_extension = FileUtil::getExtension($file['filename']);
            if ($file_extension != '') {
                if (!in_array(strtolower($file_extension), $allowedExtensionsArray) && !in_array(strtoupper(($file_extension)), $allowedExtensionsArray)) {
                    LogUtil::registerError(__f('The zip file contains at least one file with the extension <strong>%s</strong> which is not allowed. The allowed extensions are: <strong>%s</strong>.', array(
                        $file_extension,
                        str_replace(',', ', ', $allowedExtensions)), $dom));
                    return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
                }
            }
        }
        // check if user have enough space to unzip the file
        // get user used space in folder
        $userDiskUse = ModUtil::apiFunc('Files', 'user', 'get');
        $usedSpace = $userDiskUse['diskUse'];
        // get user allowed space
        $userAllowedSpace = ModUtil::func('Files', 'user', 'getUserQuota') * 1024 * 1024;
        // check if user have enough space to unzip the file
        if ($filesSize + $usedSpace > $userAllowedSpace && $userAllowedSpace != -1048576) {
            LogUtil::registerError(__f('You have not enough disk space to unzip the file. You need %s extra bytes.', $filesSize + $usedSpace - $userAllowedSpace, $dom));
            return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
        }
        if ($archive->extract(PCLZIP_OPT_PATH, ($initFolderPath . "/" . $folder)) == 0) {
            LogUtil::registerError($this->__('Failed to unzip', $dom));
            return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
        }
        // update the number of bytes used by user
        ModUtil::apiFunc('Files', 'user', 'updateUsedSpace');
        // protect the folders with the .htaccess and .locked files
        ModUtil::func('Files', 'user', 'createProtectFiles', array('folder' => $folder));
        LogUtil::registerStatus($this->__('Successfully unzipped', $dom));
        return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
    }
    
    /**
     * Get the init folder path. For a multisites installation the folder path depends on the siteDNS.
     * For a not multiple installation the folder path is stored in a module variable.
     * @author    Albert Pérez Monfort
     * @return    The initial folder path
     */
    public function getInitFolderPath()
    {
        $dom = ZLanguage::getModuleDomain('Files');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD) || !UserUtil::login()) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        if ($GLOBALS['PNConfig']['Multisites']['multi'] == 1) {
            $siteDNS = FormUtil::getPassedValue('siteDNS', '', 'GET');
            // if siteDNS is the main site the root is the initial folder. If not it is the initial folder by the site
            $rootFolderPath = ($siteDNS == $GLOBALS['PNConfig']['Multisites']['mainSiteURL']) ? $GLOBALS['PNConfig']['Multisites']['filesRealPath'] : $GLOBALS['PNConfig']['Multisites']['filesRealPath'] . '/' . $siteDNS . $GLOBALS['PNConfig']['Multisites']['siteFilesFolder'];
        } else {
            $rootFolderPath = ModUtil::getVar('Files', 'folderPath');
        }
        // checks if $rootFolderPath exists. If not user is send to error page
        if (!file_exists($rootFolderPath)) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = __f('The folder <strong>%s</strong> has not been found.', $rootFolderPath, $dom);
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        // checks if users folder exists. If user is admimistrator return initial folder otherwise return an error page
        $userFolder = $rootFolderPath . '/' . ModUtil::getVar('Files', 'usersFolder');
        if (!file_exists($userFolder)) {
            if (SecurityUtil::checkPermission('Files::', "::", ACCESS_ADMIN)) {
                return $rootFolderPath;
            } else {
                $renderer = Renderer::getInstance('Files', false);
                $errorMsg = $this->__('Directory creation error. Please contact with the administrator', $dom) . ': ' . $folderName;
                $renderer->assign('errorMsg', $errorMsg);
                return $renderer->fetch('Files_user_errorMsg.htm');
            }
        }
        // get user folder path depending on user name and if it not exists, create it
        $userFolder = ModUtil::getVar('Files', 'usersFolder') . '/' . strtolower(substr(UserUtil::getVar('uname'), 0, 1));
        if (!file_exists($rootFolderPath . '/' . $userFolder)) {
            if (!FileUtil::mkdirs($rootFolderPath . '/' . $userFolder, 0777, true)) {
                LogUtil::registerError($this->__('Directory creation error') . ': ' . $rootFolderPath . '/' . $userFolder);
                return false;
            }
            // create .htaccess and .locked files
            FileUtil::writeFile($rootFolderPath . '/' . $userFolder . '/.htaccess', $this->_HTACCESSCONTENT, true);
            FileUtil::writeFile($rootFolderPath . '/' . $userFolder . '/.locked', $this->_LOCKEDCONTENT, true);
        }
        $userFolder .= '/' . UserUtil::getVar('uname');
        if (!file_exists($rootFolderPath . '/' . $userFolder)) {
            // create user record in database
            if ($created = ModUtil::apiFunc('Files', 'user', 'createUserFilesInfo')) {
                // create dir
                if (!FileUtil::mkdirs($rootFolderPath . '/' . $userFolder, 0777, true)) {
                    LogUtil::registerError($this->__('Directory creation error') . ': ' . $rootFolderPath . '/' . $userFolder);
                    return false;
                }
                // create .htaccess and .locked files
                ModUtil::func('Files', 'user', 'createaccessfile',
                            array('folder' => $userFolder));
            }
        }
        // get user init folder
        $initFolderPath = (SecurityUtil::checkPermission('Files::', "::", ACCESS_ADMIN)) ? $rootFolderPath : $rootFolderPath . '/' . $userFolder;
        if (!file_exists($initFolderPath) || !is_writable($initFolderPath)) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = __f('The "%s" directory does not exist or is not writable.', $initFolderPath, $dom);
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        return $initFolderPath;
    }
    
    /**
     * gets the user disk quota depending on the groups where the user belong and the quota assigned to these groups
     * @author:	Albert Pérez Monfort
     * @param:	User identity
     * @return:	The allowed disk quota for the user
     */
    public function getUserQuota($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $userId = FormUtil::getPassedValue('userId', isset($args['userId']) ? $args['userId'] : UserUtil::getVar('uid'), 'POST');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        $diskQuota = -2;
        // get assigned quotas
        $groupsQuotas = ModUtil::getVar('Files', 'groupsQuota');
        $groupsQuotas = unserialize($groupsQuotas);
        foreach ($groupsQuotas as $quota) {
            $groupsQuotasArray[$quota['gid']] = array('gid' => $quota['gid'], 'quota' => $quota['quota']);
        }
        // get user groups
        $userGroups = ModUtil::apiFunc('Groups', 'user', 'getusergroups');
        foreach ($userGroups as $group) {
            // not limit
            if (array_key_exists($group['gid'], $groupsQuotasArray) && $groupsQuotasArray[$group['gid']]['quota'] == '-1') {
                return -1;
            }
            if (array_key_exists($group['gid'], $groupsQuotasArray) && $groupsQuotasArray[$group['gid']]['quota'] > $diskQuota) {
                $diskQuota = $groupsQuotasArray[$quota['gid']]['quota'];
            }
        }
        // if is not designed a disk quota is set the default quota
        // by default the disk quota is the default value
        if ($diskQuota == -2) {
            $diskQuota = ModUtil::getVar('Files', 'defaultQuota');
        }
        return $diskQuota;
    }
    
    /**
     * Set a folder as public or not public
     * @author:     Albert Pérez Monfort (aperezm@xtec.cat)
     * @param:	    folder name
     * @return:	    True if success or false in other case
     */
    public function setAsPublic($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        // get parameters from whatever input we need.
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'GET');
        $not = FormUtil::getPassedValue('not', isset($args['not']) ? $args['not'] : null, 'GET');
        $external = FormUtil::getPassedValue('external', isset($args['external']) ? $args['external'] : null, 'GET');
        $hook = FormUtil::getPassedValue('hook', isset($args['hook']) ? $args['hook'] : null, 'GET');
        // security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $returnType = ($external == 1) ? 'external' : 'user';
        $returnFunc = ($external == 1) ? 'getFiles' : 'main';
        if ($not == null) {
            $url = $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath') . '/' . $folder;
            // if the folder .tbn for thumbnails does not exists create it
            if (!file_exists($url .'/.tbn')) {
                if (FileUtil::mkdirs($url .'/.tbn', 0777, true)) {
                    // create protection files
                    ModUtil::func('Files', 'user', 'createaccessfile',
                                array('folder' => $folder .'/.tbn',
                                      'onlyHtaccess' => 1));
                }
            }
            // delete protection file
            $whereToDelete = $url . '/.locked';
            if (file_exists($whereToDelete)) {
                unlink($whereToDelete);
            }
            $whereToDelete = $url . '/.tbn/.locked';
            if (file_exists($whereToDelete)) {
                unlink($whereToDelete);
            }
        } else {
            // create protection files
            if (ModUtil::func('Files', 'user', 'createaccessfile',
                        array('folder' => $folder))) {
                ModUtil::func('Files', 'user', 'createaccessfile',
                            array('folder' => $folder .'/.tbn'));
            }
        }
        return System::redirect(ModUtil::url('Files', $returnType, $returnFunc, array('folder' => $folder, 'hook' => $hook)));
    }
    
    /**
     * create recursibily the .htaccess and .locked files in the folder because it is a public folder
     * @author:	Albert Pérez Monfort (aperezm@xtec.cat)
     * @param:	dir Folder Path
     * @return:	Objects array of the files
     */
    public function createProtectFiles($args)
    {
        $dom = ZLanguage::getModuleDomain('Files');
        $folder = FormUtil::getPassedValue('folder', isset($args['folder']) ? $args['folder'] : null, 'GET');
        // Security check
        if (!SecurityUtil::checkPermission('Files::', "::", ACCESS_ADD)) {
            return LogUtil::registerError($this->__('Error! You are not authorized to access this module.', $dom), 403);
        }
        $initFolderPath = ModUtil::func('Files', 'user', 'getInitFolderPath');
        // protection. User can not navigate out their root folder
        if ($folder == ".." || $folder == "." || strpos($folder, "..") !== false) {
            $renderer = Renderer::getInstance('Files', false);
            $errorMsg = $this->__('Invalid folder', $dom) . ': ' . $folder;
            $renderer->assign('errorMsg', $errorMsg);
            return $renderer->fetch('Files_user_errorMsg.htm');
        }
        $initFolder = $initFolderPath . "/" . $folder;
        $directoris = ModUtil::func('Files', 'user', 'getListDirRecursive', array('dir' => $initFolder));
        foreach ($directoris as $dir) {
            // not public directori
            ModUtil::func('Files', 'user', 'createaccessfile', array('folder' => $dir));
        }
        return true;
    }
    
    /**
     * get the list of information about file types based on extensions.
     * @return an array with the list of information about file types based on extensions
     */
    public function getMimetype($args)
    {
        $extension = FormUtil::getPassedValue('extension', isset($args['extension']) ? $args['extension'] : null, 'POST');
        $mimeTypes = array(
            'xxx' => array('type' => 'document/unknown', 'icon' => 'unknown.gif'),
            '3gp' => array('type' => 'video/quicktime', 'icon' => 'video.gif'),
            'ai' => array('type' => 'application/postscript', 'icon' => 'image.gif'),
            'aif' => array('type' => 'audio/x-aiff', 'icon' => 'audio.gif'),
            'aiff' => array('type' => 'audio/x-aiff', 'icon' => 'audio.gif'),
            'aifc' => array('type' => 'audio/x-aiff', 'icon' => 'audio.gif'),
            'applescript' => array('type' => 'text/plain', 'icon' => 'text.gif'),
            'asc' => array('type' => 'text/plain', 'icon' => 'text.gif'),
            'asm' => array('type' => 'text/plain', 'icon' => 'text.gif'),
            'au' => array('type' => 'audio/au', 'icon' => 'audio.gif'),
            'avi' => array('type' => 'video/x-ms-wm', 'icon' => 'avi.gif'),
            'bmp' => array('type' => 'image/bmp', 'icon' => 'image.gif'),
            'c' => array('type' => 'text/plain', 'icon' => 'text.gif'),
            'cct' => array('type' => 'shockwave/director', 'icon' => 'flash.gif'),
            'cpp' => array('type' => 'text/plain', 'icon' => 'text.gif'),
            'cs' => array('type' => 'application/x-csh', 'icon' => 'text.gif'),
            'css' => array('type' => 'text/css', 'icon' => 'text.gif'),
            'dv' => array('type' => 'video/x-dv', 'icon' => 'video.gif'),
            'dmg' => array('type' => 'application/octet-stream', 'icon' => 'dmg.gif'),
            'doc' => array('type' => 'application/msword', 'icon' => 'word.gif'),
            'docx' => array('type' => 'application/msword', 'icon' => 'docx.gif'),
            'docm' => array('type' => 'application/msword', 'icon' => 'docm.gif'),
            'dotx' => array('type' => 'application/msword', 'icon' => 'dotx.gif'),
            'dcr' => array('type' => 'application/x-director', 'icon' => 'flash.gif'),
            'dif' => array('type' => 'video/x-dv', 'icon' => 'video.gif'),
            'dir' => array('type' => 'application/x-director', 'icon' => 'flash.gif'),
            'dxr' => array('type' => 'application/x-director', 'icon' => 'flash.gif'),
            'eps' => array('type' => 'application/postscript', 'icon' => 'pdf.gif'),
            'fdf' => array('type' => 'application/pdf', 'icon' => 'pdf.gif'),
            'flv' => array('type' => 'video/x-flv', 'icon' => 'video.gif'),
            'gif' => array('type' => 'image/gif', 'icon' => 'image.gif'),
            'gtar' => array('type' => 'application/x-gtar', 'icon' => 'zip.gif'),
            'tgz' => array('type' => 'application/g-zip', 'icon' => 'zip.gif'),
            'gz' => array('type' => 'application/g-zip', 'icon' => 'zip.gif'),
            'gzip' => array('type' => 'application/g-zip', 'icon' => 'zip.gif'),
            'h' => array('type' => 'text/plain', 'icon' => 'text.gif'),
            'hpp' => array('type' => 'text/plain', 'icon' => 'text.gif'),
            'hqx' => array('type' => 'application/mac-binhex40', 'icon' => 'zip.gif'),
            'htc' => array('type' => 'text/x-component', 'icon' => 'text.gif'),
            'html' => array('type' => 'text/html', 'icon' => 'html.gif'),
            'htm' => array('type' => 'text/html', 'icon' => 'html.gif'),
            'ico' => array('type' => 'image/vnd.microsoft.icon', 'icon' => 'image.gif'),
            'java' => array('type' => 'text/plain', 'icon' => 'text.gif'),
            'jcb' => array('type' => 'text/xml', 'icon' => 'jcb.gif'),
            'jcl' => array('type' => 'text/xml', 'icon' => 'jcl.gif'),
            'jcw' => array('type' => 'text/xml', 'icon' => 'jcw.gif'),
            'jmt' => array('type' => 'text/xml', 'icon' => 'jmt.gif'),
            'jmx' => array('type' => 'text/xml', 'icon' => 'jmx.gif'),
            'jpe' => array('type' => 'image/jpeg', 'icon' => 'image.gif'),
            'jpeg' => array('type' => 'image/jpeg', 'icon' => 'image.gif'),
            'jpg' => array('type' => 'image/jpeg', 'icon' => 'image.gif'),
            'jqz' => array('type' => 'text/xml', 'icon' => 'jqz.gif'),
            'js' => array('type' => 'application/x-javascript', 'icon' => 'text.gif'),
            'latex' => array('type' => 'application/x-latex', 'icon' => 'text.gif'),
            'm' => array('type' => 'text/plain', 'icon' => 'text.gif'),
            'mov' => array('type' => 'video/quicktime', 'icon' => 'video.gif'),
            'movie' => array('type' => 'video/x-sgi-movie', 'icon' => 'video.gif'),
            'm3u' => array('type' => 'audio/x-mpegurl', 'icon' => 'audio.gif'),
            'mp3' => array('type' => 'audio/mp3', 'icon' => 'audio.gif'),
            'mp4' => array('type' => 'video/mp4', 'icon' => 'video.gif'),
            'mpeg' => array('type' => 'video/mpeg', 'icon' => 'video.gif'),
            'mpe' => array('type' => 'video/mpeg', 'icon' => 'video.gif'),
            'mpg' => array('type' => 'video/mpeg', 'icon' => 'video.gif'),
            'odt' => array(
            'type' => 'application/vnd.oasis.opendocument.text',
            'icon' => 'odt.gif'),
            'ott' => array(
            'type' => 'application/vnd.oasis.opendocument.text-template',
            'icon' => 'odt.gif'),
            'oth' => array(
            'type' => 'application/vnd.oasis.opendocument.text-web',
            'icon' => 'odt.gif'),
            'odm' => array(
            'type' => 'application/vnd.oasis.opendocument.text-master',
            'icon' => 'odm.gif'),
            'odg' => array(
            'type' => 'application/vnd.oasis.opendocument.graphics',
            'icon' => 'odg.gif'),
            'otg' => array(
            'type' => 'application/vnd.oasis.opendocument.graphics-template',
            'icon' => 'odg.gif'),
            'odp' => array(
            'type' => 'application/vnd.oasis.opendocument.presentation',
            'icon' => 'odp.gif'),
            'otp' => array(
            'type' => 'application/vnd.oasis.opendocument.presentation-template',
            'icon' => 'odp.gif'),
            'ods' => array(
            'type' => 'application/vnd.oasis.opendocument.spreadsheet',
            'icon' => 'ods.gif'),
            'ots' => array(
            'type' => 'application/vnd.oasis.opendocument.spreadsheet-template',
            'icon' => 'ods.gif'),
            'odc' => array(
            'type' => 'application/vnd.oasis.opendocument.chart',
            'icon' => 'odc.gif'),
            'odf' => array(
            'type' => 'application/vnd.oasis.opendocument.formula',
            'icon' => 'odf.gif'),
            'odb' => array(
            'type' => 'application/vnd.oasis.opendocument.database',
            'icon' => 'odb.gif'),
            'odi' => array(
            'type' => 'application/vnd.oasis.opendocument.image',
            'icon' => 'odi.gif'),
            'pct' => array('type' => 'image/pict', 'icon' => 'image.gif'),
            'pdf' => array('type' => 'application/pdf', 'icon' => 'pdf.gif'),
            'php' => array('type' => 'text/plain', 'icon' => 'text.gif'),
            'pic' => array('type' => 'image/pict', 'icon' => 'image.gif'),
            'pict' => array('type' => 'image/pict', 'icon' => 'image.gif'),
            'png' => array('type' => 'image/png', 'icon' => 'image.gif'),
            'pps' => array(
            'type' => 'application/vnd.ms-powerpoint',
            'icon' => 'powerpoint.gif'),
            'ppt' => array(
            'type' => 'application/vnd.ms-powerpoint',
            'icon' => 'powerpoint.gif'),
            'pptx' => array('type' => 'application/vnd.ms-powerpoint', 'icon' => 'pptx.gif'),
            'pptm' => array('type' => 'application/vnd.ms-powerpoint', 'icon' => 'pptm.gif'),
            'potx' => array('type' => 'application/vnd.ms-powerpoint', 'icon' => 'potx.gif'),
            'potm' => array('type' => 'application/vnd.ms-powerpoint', 'icon' => 'potm.gif'),
            'ppam' => array('type' => 'application/vnd.ms-powerpoint', 'icon' => 'ppam.gif'),
            'ppsx' => array('type' => 'application/vnd.ms-powerpoint', 'icon' => 'ppsx.gif'),
            'ppsm' => array('type' => 'application/vnd.ms-powerpoint', 'icon' => 'ppsm.gif'),
            'ps' => array('type' => 'application/postscript', 'icon' => 'pdf.gif'),
            'qt' => array('type' => 'video/quicktime', 'icon' => 'video.gif'),
            'ra' => array('type' => 'audio/x-realaudio', 'icon' => 'audio.gif'),
            'ram' => array('type' => 'audio/x-pn-realaudio', 'icon' => 'audio.gif'),
            'rhb' => array('type' => 'text/xml', 'icon' => 'xml.gif'),
            'rm' => array('type' => 'audio/x-pn-realaudio', 'icon' => 'audio.gif'),
            'rtf' => array('type' => 'text/rtf', 'icon' => 'text.gif'),
            'rtx' => array('type' => 'text/richtext', 'icon' => 'text.gif'),
            'sh' => array('type' => 'application/x-sh', 'icon' => 'text.gif'),
            'sit' => array('type' => 'application/x-stuffit', 'icon' => 'zip.gif'),
            'smi' => array('type' => 'application/smil', 'icon' => 'text.gif'),
            'smil' => array('type' => 'application/smil', 'icon' => 'text.gif'),
            'sqt' => array('type' => 'text/xml', 'icon' => 'xml.gif'),
            'svg' => array('type' => 'image/svg+xml', 'icon' => 'image.gif'),
            'svgz' => array('type' => 'image/svg+xml', 'icon' => 'image.gif'),
            'swa' => array('type' => 'application/x-director', 'icon' => 'flash.gif'),
            'swf' => array('type' => 'application/x-shockwave-flash', 'icon' => 'flash.gif'),
            'swfl' => array('type' => 'application/x-shockwave-flash', 'icon' => 'flash.gif'),
            'sxw' => array('type' => 'application/vnd.sun.xml.writer', 'icon' => 'odt.gif'),
            'stw' => array(
            'type' => 'application/vnd.sun.xml.writer.template',
            'icon' => 'odt.gif'),
            'sxc' => array('type' => 'application/vnd.sun.xml.calc', 'icon' => 'odt.gif'),
            'stc' => array(
            'type' => 'application/vnd.sun.xml.calc.template',
            'icon' => 'odt.gif'),
            'sxd' => array('type' => 'application/vnd.sun.xml.draw', 'icon' => 'odt.gif'),
            'std' => array(
            'type' => 'application/vnd.sun.xml.draw.template',
            'icon' => 'odt.gif'),
            'sxi' => array('type' => 'application/vnd.sun.xml.impress', 'icon' => 'odt.gif'),
            'sti' => array(
            'type' => 'application/vnd.sun.xml.impress.template',
            'icon' => 'odt.gif'),
            'sxg' => array(
            'type' => 'application/vnd.sun.xml.writer.global',
            'icon' => 'odt.gif'),
            'sxm' => array('type' => 'application/vnd.sun.xml.math', 'icon' => 'odt.gif'),
            'tar' => array('type' => 'application/x-tar', 'icon' => 'zip.gif'),
            'tif' => array('type' => 'image/tiff', 'icon' => 'image.gif'),
            'tiff' => array('type' => 'image/tiff', 'icon' => 'image.gif'),
            'tex' => array('type' => 'application/x-tex', 'icon' => 'text.gif'),
            'texi' => array('type' => 'application/x-texinfo', 'icon' => 'text.gif'),
            'texinfo' => array('type' => 'application/x-texinfo', 'icon' => 'text.gif'),
            'tsv' => array('type' => 'text/tab-separated-values', 'icon' => 'text.gif'),
            'txt' => array('type' => 'text/plain', 'icon' => 'text.gif'),
            'wav' => array('type' => 'audio/wav', 'icon' => 'audio.gif'),
            'wmv' => array('type' => 'video/x-ms-wmv', 'icon' => 'avi.gif'),
            'asf' => array('type' => 'video/x-ms-asf', 'icon' => 'avi.gif'),
            'xdp' => array('type' => 'application/pdf', 'icon' => 'pdf.gif'),
            'xfd' => array('type' => 'application/pdf', 'icon' => 'pdf.gif'),
            'xfdf' => array('type' => 'application/pdf', 'icon' => 'pdf.gif'),
            'xls' => array('type' => 'application/vnd.ms-excel', 'icon' => 'excel.gif'),
            'xlsx' => array('type' => 'application/vnd.ms-excel', 'icon' => 'xlsx.gif'),
            'xlsm' => array('type' => 'application/vnd.ms-excel', 'icon' => 'xlsm.gif'),
            'xltx' => array('type' => 'application/vnd.ms-excel', 'icon' => 'xltx.gif'),
            'xltm' => array('type' => 'application/vnd.ms-excel', 'icon' => 'xltm.gif'),
            'xlsb' => array('type' => 'application/vnd.ms-excel', 'icon' => 'xlsb.gif'),
            'xlam' => array('type' => 'application/vnd.ms-excel', 'icon' => 'xlam.gif'),
            'xml' => array('type' => 'application/xml', 'icon' => 'xml.gif'),
            'xsl' => array('type' => 'text/xml', 'icon' => 'xml.gif'),
            'zip' => array('type' => 'application/zip', 'icon' => 'zip.gif'));
        $return = $mimeTypes[$extension];
        if ($return['type'] == '') {
            $return = $mimeTypes['xxx'];
        }
        return $return;
    }
}

