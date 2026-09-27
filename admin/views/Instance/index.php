<?php

use Nails\Webhooks\Admin\Controller\Instance;
use Nails\Webhooks\Resource\Instance as InstanceResource;
use Nails\Webhooks\Service\Webhook;

/**
 * @var InstanceResource[] $aInstances
 * @var Webhook            $oWebhooks
 */

?>
<div class="group-webhooks instances">
    <p>
        <a class="btn btn-primary" href="<?=htmlspecialchars(Instance::url('create'))?>">New instance</a>
    </p>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Label</th>
                    <th>Definition</th>
                    <th>Enabled</th>
                    <th>URL</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php

                if (empty($aInstances)) {
                    ?>
                    <tr>
                        <td colspan="5" class="no-data">No webhook instances yet.</td>
                    </tr>
                    <?php
                } else {
                    foreach ($aInstances as $oInstance) {
                        $sSlug = (string) $oInstance->definition_slug;
                        $sUrl  = $oWebhooks->has($sSlug)
                            ? $oWebhooks->get($sSlug)->url((string) $oInstance->token)
                            : '';
                        ?>
                        <tr>
                            <td><?=htmlspecialchars((string) $oInstance->label)?></td>
                            <td><code><?=htmlspecialchars($sSlug)?></code></td>
                            <td><?=$oInstance->isEnabled() ? 'Yes' : 'No'?></td>
                            <td><code><?=htmlspecialchars($sUrl)?></code></td>
                            <td>
                                <a class="btn btn-default btn-xs" href="<?=htmlspecialchars(Instance::url('edit/' . $oInstance->id))?>">Edit</a>
                            </td>
                        </tr>
                        <?php
                    }
                }

                ?>
            </tbody>
        </table>
    </div>
</div>
