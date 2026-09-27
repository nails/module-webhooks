<?php

use Nails\Webhooks\Resource\Delivery;

/**
 * @var Delivery $oDelivery
 * @var string   $sLog
 */

?>
<div class="group-webhooks delivery-log">
    <dl>
        <dt>When</dt>
        <dd><?=htmlspecialchars((string) $oDelivery->created)?></dd>
        <dt>Definition</dt>
        <dd><code><?=htmlspecialchars((string) $oDelivery->definition_slug)?></code></dd>
        <dt>Status</dt>
        <dd><?=htmlspecialchars((string) $oDelivery->status)?> (HTTP <?=htmlspecialchars((string) $oDelivery->http_status)?>)</dd>
        <dt>Summary</dt>
        <dd><?=htmlspecialchars((string) $oDelivery->summary)?></dd>
        <dt>Duration</dt>
        <dd><?=htmlspecialchars((string) $oDelivery->duration_ms)?> ms</dd>
    </dl>
    <h3>File log</h3>
    <?php

    if ($sLog === '') {
        ?>
        <p class="text-muted">No lines for this delivery are in the log file.</p>
        <?php
    } else {
        ?>
        <pre><?=htmlspecialchars($sLog)?></pre>
        <?php
    }

    ?>
</div>
