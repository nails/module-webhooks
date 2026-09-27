<?php

use Nails\Webhooks\Admin\Controller\Instance as InstanceController;
use Nails\Webhooks\Definition;
use Nails\Webhooks\Resource\Instance as InstanceResource;

/**
 * @var InstanceResource|null $oInstance
 * @var Definition[]          $aDefinitions
 * @var string                $sDefinitionSlug
 * @var Definition|null       $oDefinition
 */

$bEditing = $oInstance !== null;
$sAction  = $bEditing
    ? InstanceController::url('edit/' . $oInstance->id)
    : InstanceController::url('create');
$aConfig  = $oInstance ? $oInstance->configArray() : (array) ($_POST['config'] ?? []);
$sLabel   = $oInstance ? (string) $oInstance->label : (string) ($_POST['label'] ?? '');
$bEnabled = $oInstance ? $oInstance->isEnabled() : !empty($_POST['is_enabled']);
if (!$bEditing && $sDefinitionSlug === '' && !empty($_GET['definition_slug'])) {
    $sDefinitionSlug = (string) $_GET['definition_slug'];
}

?>
<div class="group-webhooks instance-form">
    <?php

    if (!$bEditing) {
        ?>
        <form method="get" action="<?=htmlspecialchars($sAction)?>">
            <div class="form-group">
                <label for="definition_slug">Definition</label>
                <select name="definition_slug" id="definition_slug" class="form-control" onchange="this.form.submit()">
                    <option value="">Choose a webhook</option>
                    <?php

                    foreach ($aDefinitions as $oOption) {
                        ?>
                        <option value="<?=htmlspecialchars($oOption->slug)?>" <?=$oOption->slug === $sDefinitionSlug ? 'selected' : ''?>>
                            <?=htmlspecialchars($oOption->handler()->getLabel())?> (<?=htmlspecialchars($oOption->slug)?>)
                        </option>
                        <?php
                    }

                    ?>
                </select>
            </div>
        </form>
        <?php
    }

    ?>
    <?=form_open($sAction)?>
        <?php

        if ($bEditing) {
            ?>
            <div class="form-group">
                <label>Definition</label>
                <p><code><?=htmlspecialchars((string) $oInstance->definition_slug)?></code></p>
            </div>
            <div class="form-group">
                <label>URL</label>
                <p><code><?=htmlspecialchars($oDefinition ? $oDefinition->url((string) $oInstance->token) : '')?></code></p>
            </div>
            <?php

            if ($oDefinition && $oDefinition->isProtected()) {
                ?>
                <div class="form-group">
                    <label>Secret</label>
                    <p><code><?=htmlspecialchars((string) $oInstance->secret)?></code></p>
                </div>
                <?php
            }

            if ($oInstance->subscription_status) {
                ?>
                <div class="form-group">
                    <label>Subscription</label>
                    <p><?=htmlspecialchars((string) $oInstance->subscription_status)?></p>
                </div>
                <?php
            }
        } else {
            ?>
            <input type="hidden" name="definition_slug" value="<?=htmlspecialchars($sDefinitionSlug)?>">
            <?php
        }

        ?>
        <div class="form-group">
            <label for="label">Label</label>
            <input type="text" name="label" id="label" class="form-control" value="<?=htmlspecialchars($sLabel)?>">
        </div>
        <div class="checkbox">
            <label>
                <input type="checkbox" name="is_enabled" value="1" <?=$bEnabled ? 'checked' : ''?>>
                Enabled
            </label>
        </div>
        <?php

        if ($oDefinition) {
            foreach ($oDefinition->configFields() as $sKey => $aField) {
                $sFieldLabel = (string) ($aField['label'] ?? $sKey);
                $sValue      = (string) ($aConfig[$sKey] ?? '');
                ?>
                <div class="form-group">
                    <label for="config-<?=htmlspecialchars($sKey)?>"><?=htmlspecialchars($sFieldLabel)?></label>
                    <input type="text" class="form-control" id="config-<?=htmlspecialchars($sKey)?>" name="config[<?=htmlspecialchars($sKey)?>]" value="<?=htmlspecialchars($sValue)?>">
                </div>
                <?php
            }
        }

        ?>
        <button type="submit" class="btn btn-primary"><?=$bEditing ? 'Save' : 'Create'?></button>
    <?=form_close()?>

    <?php

    if ($bEditing) {
        ?>
        <hr>
        <?=form_open(InstanceController::url('rotateToken/' . $oInstance->id))?>
            <button type="submit" class="btn btn-default">Rotate URL token</button>
        <?=form_close()?>
        <?php

        if ($oDefinition && $oDefinition->isProtected()) {
            echo form_open(InstanceController::url('rotateSecret/' . $oInstance->id));
            echo '<button type="submit" class="btn btn-default">Rotate secret</button>';
            echo form_close();
        }

        echo form_open(InstanceController::url('delete/' . $oInstance->id));
        echo '<button type="submit" class="btn btn-danger">Delete</button>';
        echo form_close();
    }

    ?>
</div>
