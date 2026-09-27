<?php

namespace Nails\Webhooks\Admin\Controller;

use Nails\Admin\Controller\Base;
use Nails\Admin\Factory\Nav;
use Nails\Common\Service\Input;
use Nails\Factory;
use Nails\Webhooks\Admin\Permission;
use Nails\Webhooks\Constants;
use Nails\Webhooks\Definition;
use Nails\Webhooks\Interfaces\Subscribable;
use Nails\Webhooks\Model\Instance as InstanceModel;
use Nails\Webhooks\Resource\Instance as InstanceResource;
use Nails\Webhooks\Service\Webhook;
use Throwable;

class Instance extends Base
{
    public static function announce(): Nav|array|null
    {
        if (!userHasPermission(Permission\Instance\Manage::class)) {
            return null;
        }

        /** @var Nav $oNavGroup */
        $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
        $oNavGroup
            ->setLabel('Webhooks')
            ->setIcon('fa-plug')
            ->addAction('Instances');

        return $oNavGroup;
    }

    public function index(): void
    {
        $this->authorise();

        /** @var InstanceModel $oModel */
        $oModel = Factory::model('Instance', Constants::MODULE_SLUG);

        $this
            ->addBreadcrumb('Webhooks')
            ->addBreadcrumb('Instances')
            ->setData('aInstances', $oModel->getAll())
            ->setData('oWebhooks', $this->webhooks())
            ->loadView('index');
    }

    public function create(): void
    {
        $this->authorise();

        if (Input::post()) {
            $this->save(null);

            return;
        }

        $this->form(null);
    }

    public function edit(int $iId): void
    {
        $this->authorise();
        $oInstance = $this->requireInstance($iId);

        if (Input::post()) {
            $this->save($oInstance);

            return;
        }

        $this->form($oInstance);
    }

    public function rotateSecret(int $iId): void
    {
        $this->authorise();
        $this->requirePost();
        $oInstance = $this->requireInstance($iId);
        $oDefinition = $this->definitionFor($oInstance);
        if ($oDefinition === null || !$oDefinition->isProtected()) {
            show404();
            exit;
        }

        $sSecret = bin2hex(random_bytes(32));
        $this->model()->update($oInstance->id, ['secret' => $sSecret]);
        $oInstance->secret = $sSecret;
        $this->syncSubscription($oInstance, false);
        $this->oUserFeedback->success('Secret rotated.');
        redirect(static::url('edit/' . $oInstance->id));
    }

    public function rotateToken(int $iId): void
    {
        $this->authorise();
        $this->requirePost();
        $oInstance = $this->requireInstance($iId);
        $sToken    = bin2hex(random_bytes(32));
        $this->model()->update($oInstance->id, ['token' => $sToken]);
        $oInstance->token = $sToken;
        $this->syncSubscription($oInstance, false);
        $this->oUserFeedback->success('URL token rotated. Update the provider with the new URL.');
        redirect(static::url('edit/' . $oInstance->id));
    }

    public function delete(int $iId): void
    {
        $this->authorise();
        $this->requirePost();
        $oInstance = $this->requireInstance($iId);
        $this->syncSubscription($oInstance, true);
        $this->model()->delete($oInstance->id);
        $this->oUserFeedback->success('Instance deleted.');
        redirect(static::url());
    }

    private function save(?InstanceResource $oInstance): void
    {
        $sSlug = $oInstance
            ? (string) $oInstance->definition_slug
            : (string) Input::post('definition_slug');
        $oDefinition = $this->webhooks()->has($sSlug) ? $this->webhooks()->get($sSlug) : null;

        if ($oDefinition === null || !$oDefinition->isConfigurable()) {
            $this->oUserFeedback->error('Choose a configurable webhook.');
            $this->form($oInstance, $sSlug);

            return;
        }

        $sLabel = trim((string) Input::post('label'));
        if ($sLabel === '') {
            $this->oUserFeedback->error('Label is required.');
            $this->form($oInstance, $sSlug);

            return;
        }

        $aConfig = [];
        $aPosted = Input::post('config');
        $aPosted = is_array($aPosted) ? $aPosted : [];
        foreach ($oDefinition->configFields() as $sKey => $aField) {
            $mValue = $aPosted[$sKey] ?? null;
            $sRules = (string) ($aField['rules'] ?? '');
            if (str_contains($sRules, 'required') && ($mValue === null || $mValue === '')) {
                $this->oUserFeedback->error(($aField['label'] ?? $sKey) . ' is required.');
                $this->form($oInstance, $sSlug);

                return;
            }
            $aConfig[$sKey] = $mValue;
        }

        $bEnabled = (bool) Input::post('is_enabled');
        $bWasEnabled = $oInstance ? $oInstance->isEnabled() : false;

        if ($oInstance === null) {
            $iId = $this->model()->create([
                'definition_class' => $oDefinition->class,
                'definition_slug'  => $oDefinition->slug,
                'token'            => bin2hex(random_bytes(32)),
                'label'            => $sLabel,
                'is_enabled'       => $bEnabled ? 1 : 0,
                'secret'           => $oDefinition->isProtected() ? bin2hex(random_bytes(32)) : null,
                'config'           => $aConfig,
            ]);
            if (!$iId) {
                $this->oUserFeedback->error('Could not create the instance.');
                $this->form(null, $sSlug);

                return;
            }
            $oSaved = $this->requireInstance((int) $iId);
            if ($bEnabled) {
                $this->syncSubscription($oSaved, false);
            }
            $this->oUserFeedback->success('Instance created.');
            redirect(static::url('edit/' . $iId));
        }

        $this->model()->update($oInstance->id, [
            'label'      => $sLabel,
            'is_enabled' => $bEnabled ? 1 : 0,
            'config'     => $aConfig,
        ]);
        $oSaved = $this->requireInstance((int) $oInstance->id);
        if ($bWasEnabled && !$bEnabled) {
            $this->syncSubscription($oSaved, true);
        } elseif ($bEnabled) {
            $this->syncSubscription($oSaved, false);
        }
        $this->oUserFeedback->success('Instance saved.');
        redirect(static::url('edit/' . $oInstance->id));
    }

    private function form(?InstanceResource $oInstance, ?string $sSlug = null): void
    {
        $sSlug ??= $oInstance
            ? (string) $oInstance->definition_slug
            : (string) (Input::post('definition_slug') ?: Input::get('definition_slug'));
        $oDefinition = ($sSlug !== '' && $this->webhooks()->has($sSlug)) ? $this->webhooks()->get($sSlug) : null;

        $this
            ->addBreadcrumb('Webhooks')
            ->addBreadcrumb('Instances', static::url())
            ->addBreadcrumb($oInstance ? 'Edit' : 'Create')
            ->setData('oInstance', $oInstance)
            ->setData('aDefinitions', $this->webhooks()->configurable())
            ->setData('sDefinitionSlug', $sSlug)
            ->setData('oDefinition', $oDefinition)
            ->loadView('form');
    }

    private function syncSubscription(InstanceResource $oInstance, bool $bRemove): void
    {
        $oDefinition = $this->definitionFor($oInstance);
        if ($oDefinition === null) {
            return;
        }

        $oHandler = $oDefinition->handler();
        if (!$oHandler instanceof Subscribable) {
            return;
        }

        try {
            if ($bRemove) {
                $oHandler->unsubscribe($oInstance);
                $sStatus = 'unsubscribed';
            } else {
                $oHandler->subscribe($oInstance);
                $sStatus = $oHandler->subscriptionStatus($oInstance);
            }
            $this->model()->update($oInstance->id, ['subscription_status' => $sStatus]);
        } catch (Throwable $oError) {
            $this->oUserFeedback->error($oError->getMessage());
        }
    }

    private function definitionFor(InstanceResource $oInstance): ?Definition
    {
        $sSlug = (string) $oInstance->definition_slug;

        return $this->webhooks()->has($sSlug) ? $this->webhooks()->get($sSlug) : null;
    }

    private function requireInstance(int $iId): InstanceResource
    {
        $oInstance = $this->model()->getById($iId);
        if (!$oInstance instanceof InstanceResource) {
            show404();
            exit;
        }

        return $oInstance;
    }

    private function requirePost(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            show404();
            exit;
        }
    }

    private function authorise(): void
    {
        if (!userHasPermission(Permission\Instance\Manage::class)) {
            unauthorised();
        }
    }

    private function model(): InstanceModel
    {
        /** @var InstanceModel $oModel */
        $oModel = Factory::model('Instance', Constants::MODULE_SLUG);

        return $oModel;
    }

    private function webhooks(): Webhook
    {
        /** @var Webhook $oWebhooks */
        $oWebhooks = Factory::service('Webhook', Constants::MODULE_SLUG);

        return $oWebhooks;
    }
}
