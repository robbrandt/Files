<script src="modules/Scribite/includes/xinha/popups/popup.js" type="text/javascript"></script>
<script src="modules/Files/javascript/getFiles.js" type="text/javascript"></script>
<tr id=image_{$file.name} name="image_{$file.name}">
{*<span id="image_{$file.name}" name="image_{$file.name}">*}
    {*<div class="imageBoxContent">*}
        <td><img src="file.php?file={$folderPath}{if $folderPath|substr:-1 neq '/'}/{/if}.tbn/{$file.name}" width="{$file.viewWidth}" height="{$file.viewHeight}"/></td>
        <td>{$file.width} x {$file.height} ({$file.factor})</td>
        <td><a href="javascript:modifySize('{$folderName}','{$file.name}','{$file.factor}','reduce');">
                {img modname='Files' src='reduce.gif' __alt="Reduce size" __title="Reduce size"}
            </a>
            <a href="javascript:modifySize('{$folderName}','{$file.name}','{$file.factor}','increase');">
                {img modname='Files' src='increase.gif' __alt="Increase size" __title="Increase size"}
            </a>
            <a href="{modurl modname='Files' type='user' external='1' func='action' do='delete' fileName=$file.name folder=$folderName|replace:'/':'|' thumb=1 hook=$hook}">
                {img modname='core' src='14_layer_deletelayer.png' set='icons/extrasmall' __alt="Delete thumbnail" __title="Delete thumbnail"}
            </a>
            {if $hook neq 1}
            <a href="" onclick="javascript:returnEditor('insertImg',true)" title="Insert" >
                {img modname='core' set='icons/extrasmall' src='inbox.png' __title="Insert" __alt="Insert"}
            </a> 
            {else}
            <a href="" onclick="insertAndClose('file.php?file={$folderPath}{if $folderPath|substr:-1 neq '/'}/{/if}.tbn/{$file.name}');" title="Insert" >
                {img modname='core' set='icons/extrasmall' src='inbox.png' __title="Insert" __alt="Insert"}
            </a> 
            {/if}
        </td>
    {*</div>*]
{*</span>*}
</tr>
