{include file="Files_admin_menu.tpl"}
<div class="z-admincontainer">
    <div class="z-adminpageicon">{img modname='core' src='configure.png' set='icons/large'}</div>
    <h2>{gt text="Modify configuration"}</h2>
    {if $fileFileInModule OR $fileFileNotInRoot}
        {if $fileFileNotInRoot}
            <p class="z-warningmsg">{gt text="You should move the file file.php from modules/Files/Resources/extras to the zikula root directory"}</p>
        {else}
            {if $fileFileInModule}
                <p class="z-warningmsg">{gt text="You should remove the file file.php from modules/Files/Resources/extras"}</p>
            {/if}
        {/if}
    {/if}
    <form class="z-form" enctype="application/x-www-form-urlencoded" method="post" id="conf" action="{modurl modname='Files' type='admin' func='updateconfig'}">
        <div>
            <input type="hidden" name="csrftoken" value="{insert name='csrftoken'}" />
            <fieldset>
                <legend>{gt text="Settings"}</legend>
                <fieldset>
                <div class="z-formrow">
                    {if $check.config eq multisites}
                        <p class="z-formnote">{gt text="Files system physical path"}: {gt text="Defined in multisites configuration"}{if $agora}{gt text=" and implemends agora functions"}{/if}.</p>
                    {else}
                        <p class="z-formnote">{gt text="Files system physical path"}: <b>{$check.folderPath}</b>.
                        {if $check.config eq config_site}
                            {gt text="Defined in config file."}</p>
                        {else}
                            {gt text="Default configuration."}</p>
                        {/if}
                    {/if}
                </div>
                </fieldset>
                <div class="z-formrow">
                    <label for="usersFolder">{gt text="Users folder"}</label>
                <input type="text" id="usersFolder" name="usersFolder" size="30" value="{$moduleVars.usersFolder}" />
                {if $usersFolderProblem}
                    <p class="z-formnote z-errormsg">{gt text="The folder does not exist or it is not writable"}</p>
                {/if}
                </div>
            
                <div class="z-formrow">
                    <label for="showHideFiles">{gt text="Show hidden files"}</label>
                    <select id="showHideFiles" name="showHideFiles">
                        <option value="0">{gt text="Do not show to anyone"}</option>
                        <option {if $moduleVars.showHideFiles eq 2}selected{/if} value="2">{gt text="Only show to administrators"}</option>
                        <option {if $moduleVars.showHideFiles eq 1}selected{/if} value="1">{gt text="Show to everyone"}</option>
                    </select>
                </div>
                <div class="z-formrow">
                    <label for="allowedExtensions">{gt text="Allowed extensions (comma separated list)"}</label>
                    <input type="text" id="allowedExtensions" name="allowedExtensions" size="30" value="{$moduleVars.allowedExtensions}" />
                </div>
                <div class="z-formrow">
                    <label for="editableExtensions">{gt text="Editable extensions (comma separated list)"}</label>
                    <input type="text" id="editableExtensions" name="editableExtensions" size="30" value="{$moduleVars.editableExtensions}" />
                </div>                
                <div class="z-formrow">
                    <label for="filesMaxSize">{gt text="Maximum file size"}</label>
                    <span>
                        <input type="text" id="filesMaxSize" name="filesMaxSize" size="10" value="{$moduleVars.filesMaxSize}" />
                        {gt text="bytes"}
                    </span>
                </div>
                <div class="z-formrow">
                    <label for="maxWidth">{gt text="Default width for images in editor"}</label>
                    <span>
                        <input type="text" id="maxWidth" name="maxWidth" size="10" value="{$moduleVars.maxWidth}" />
                        {gt text="pixel"}
                    </span>
                </div>
                <div class="z-formrow">
                    <label for="maxHeight">{gt text="Default height for images in editor"}</label>
                    <span>
                        <input type="text" id="maxHeight" name="maxHeight" size="10" value="{$moduleVars.maxHeight}" />
                        {gt text="pixel"}
                    </span>
                </div>
                <div class="z-formrow">
                    <label for="defaultQuota">{gt text="Default disk quota"}</label>
                    <span>
                        <input type="text" id="defaultQuota" name="defaultQuota" size="10" value="{$moduleVars.defaultQuota}" />
                        {gt text="Mb"}
                    </span>
                </div>
            </fieldset>
            <div class="z-formbuttons">
                {button src='button_ok.png' set='icons/small' __alt="Save the changes" __title="Save the changes"}
            </div>
        </div>
    </form>
    <div class="z-form">
        <fieldset>
            <legend>{gt text="Disk quotas for groups"}</legend>
            <div id="quotaTable">{$quotasTable}</div>
        </fieldset>
    </div>
</div>
