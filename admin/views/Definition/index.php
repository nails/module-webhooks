<?php

use Nails\Webhooks\Definition;

/**
 * @var Definition[] $aDefinitions
 * @var string       $sDeliveryUrl
 */

?>
<div class="group-webhooks definitions">
    <p>Webhook definitions are classes discovered under <code>src/Webhooks</code>. The URL is derived from the package and the class name.</p>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Label</th>
                    <th>Slug</th>
                    <th>Component</th>
                    <th>Flavour</th>
                    <th>Protection</th>
                    <th>Shape</th>
                    <th>Dedupe</th>
                    <th>URL</th>
                </tr>
            </thead>
            <tbody>
                <?php

                if (empty($aDefinitions)) {
                    ?>
                    <tr>
                        <td colspan="8" class="no-data">No webhook definitions have been discovered.</td>
                    </tr>
                    <?php
                } else {
                    foreach ($aDefinitions as $oDefinition) {
                        $sDeliveries = $sDeliveryUrl . '?keywords=' . rawurlencode($oDefinition->slug);
                        ?>
                        <tr>
                            <td>
                                <strong><?=htmlspecialchars($oDefinition->handler()->getLabel())?></strong>
                                <?php

                                if ($oDefinition->handler()->getDescription() !== '') {
                                    ?>
                                    <small class="text-muted d-block"><?=htmlspecialchars($oDefinition->handler()->getDescription())?></small>
                                    <?php
                                }

                                ?>
                            </td>
                            <td><code><?=htmlspecialchars($oDefinition->slug)?></code></td>
                            <td><?=htmlspecialchars($oDefinition->componentName)?></td>
                            <td><?=htmlspecialchars($oDefinition->flavour())?></td>
                            <td><?=htmlspecialchars($oDefinition->protection())?></td>
                            <td><?=$oDefinition->isConfigurable() ? 'Configurable' : 'Singleton'?></td>
                            <td><?=$oDefinition->allowsDuplicates() ? 'Off' : 'On'?></td>
                            <td>
                                <?php

                                if ($oDefinition->isConfigurable()) {
                                    echo 'Per instance';
                                } else {
                                    ?>
                                    <code><?=htmlspecialchars($oDefinition->url())?></code>
                                    <?php
                                }

                                ?>
                                <a class="btn btn-default btn-xs" href="<?=htmlspecialchars($sDeliveries)?>">Deliveries</a>
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
