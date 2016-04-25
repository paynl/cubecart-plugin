<form action="{$VAL_SELF}" method="post" enctype="multipart/form-data">
  <div id="Paynl" class="tab_content">
	<h3>{$TITLE}</h3>
	<fieldset><legend>Pay.nl {$LANG.module.config_settings}</legend>
    {if  $noConnection}
      <div class="warnText" title="{$LANG.common.click_to_close}">
          {$LANG.paynl.no_or_wrong_credentials}
      </div>
    {/if}
    <div><label for="status">{$LANG.common.status}</label><span><input type="hidden" name="module[status]" id="status" class="toggle" value="{$MODULE.status}" /></span></div>
	  <div><label for="position">{$LANG.module.position}</label><span><input type="text" name="module[position]" id="position" class="textbox number" value="{$MODULE.position|default:'1'}" /></span></div>
          <div style="height:17px;color:#999"><label>&nbsp;</label><span style="clear:both">{$LANG.paynl.position_info}</span></div>
          <div>&nbsp;</div>
    <div><label for="default">{$LANG.common.default}</label><span><input type="hidden" name="module[default]" id="default" class="toggle" value="{$MODULE.default}" /></span></div>
    <input name="module[desc]" id="desc" class="textbox" type="hidden" value="{$merchantImage}" />
	  <div><label for="apitoken">API token</label><span><input type="text" name="module[apitoken]" id="apitoken" class="textbox" value="{$MODULE.apitoken|default:''}" /></span></div>
          <div style="height:17px;color:#999"><label>&nbsp;</label><span>{$LANG.paynl.api_token_info}</span></div>
	  <div><label for="service_id">Service ID</label><span><input type="text" name="module[service_id]" id="service_id" class="textbox" value="{$MODULE.service_id|default:''}" /></span></div>
          <div style="height:17px;color:#999"><label>&nbsp;</label><span>{$LANG.paynl.service_id_info}</span></div>
	  </fieldset>
          
    <fieldset>
      <legend>{$LANG.paynl.status_settings}</legend>
      
      <div>
        <label><strong>{$LANG.paynl.payment_is}</strong></label>
        <span><strong>{$LANG.paynl.orderstatus_becomes}</strong></span>
      </div><br>
      
      <div><label>{$LANG.paynl.PAID}</label>
        <span>
          <select name="module[paidOrderstatus]">
            {html_options options=$orderOptions selected=$paidOrderstatus}
          </select>
        </span>
      </div>
      
      <div><label>{$LANG.paynl.PENDING}</label>
        <span>
          <select name="module[pendingOrderstatus]">
            {html_options options=$orderOptions selected=$pendingOrderstatus}
          </select>
      </div>
      
      <div><label>{$LANG.paynl.CANCEL}</label>
        <span>
          <select name="module[cancelOrderstatus]">
            {html_options options=$orderOptions selected=$cancelOrderstatus}
          </select>
        </span>
      </div>
      
      <div><label>{$LANG.paynl.FAILED}</label>
        <span>
          <select name="module[failedOrderstatus]">
            {html_options options=$orderOptions selected=$failedOrderstatus}
          </select>
        </span>
      </div>
    </fieldset>
          
  </div>
  {$MODULE_ZONES}
  <div class="form_control"><input type="submit" name="save" value="{$LANG.common.save}" /></div>
  <input type="hidden" name="token" value="{$SESSION_TOKEN}" />
</form>