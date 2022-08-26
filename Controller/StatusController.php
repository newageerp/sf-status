<?php

namespace Newageerp\SfStatus\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Newageerp\SfBaseEntity\Controller\OaBaseController;
use Newageerp\SfControlpanel\Console\LocalConfigUtils;

/**
 * @Route(path="/app/nae-core/status")
 */
class StatusController extends OaBaseController
{

    /**
     * @Route(path="/element-status", methods={"POST"})
     * @param Request $request
     * @return Response
     */
    public function getElementStatus(Request $request)
    {
        $request = $this->transformJsonBody($request);

        $user = $this->findUser($request);
        if (!$user) {
            throw new Exception('Invalid user');
        }
        AuthService::getInstance()->setUser($user);

        $schema = $request->get('schema');
        $type = $request->get('type');
        $elementId = $request->get('id');

        $statusGetter = 'get' . ucfirst($type);

        $allStatuses = LocalConfigUtils::getCpConfigFileData('statuses');

        $statuses = array_filter(
            $allStatuses,
            function ($item) use ($schema, $type) {
                return $item['config']['entity'] === $schema && $item['config']['type'] === $type;
            }
        );

        $entityClass = $this->convertSchemaToEntity($schema);
        $repo = $this->getEm()->getRepository($entityClass);

        $element = $repo->find($elementId);

        $data = [];
        $currentStatus = [];

        if ($element) {
            $elementStatus = $element->$statusGetter();

            $scopes = [];
            if (method_exists($element, 'getScopes')) {
                $scopes = $element->getScopes();
            }

            foreach ($statuses as $statusData) {
                $status = $statusData['config']['status'];
                $statusDisableScope = 'cant-' . $statusData['config']['type'] . '-' . $status;
                $statusDisableScopeAll = 'cant-' . $statusData['config']['type'] . '-all';

                $disabled = in_array($statusDisableScope, $scopes) || in_array($statusDisableScopeAll, $scopes);

                $tooltip = '';
                if (isset($statusData['config']['tooltip'])) {
                    $tooltip = $statusData['config']['tooltip'];
                }
                if ($disabled && method_exists($element, 'getDisabledStatusTooltip')) {
                    $tooltip = $element->getDisabledStatusTooltip($status, $statusData['config']['type']);
                } else if (method_exists($element, 'getStatusTooltip')) {
                    $tooltip = $element->getStatusTooltip($status, $statusData['config']['type']);
                }

                $el = [
                    'text' => $statusData['config']['text'],
                    'status' => $statusData['config']['status'],
                    'badgeVariant' => isset($statusData['config']['badgeVariant']) ? $statusData['config']['badgeVariant'] : '',
                    'disabled' => $disabled,
                    'active' => $statusData['config']['status'] === $elementStatus,
                    'tooltip' => $tooltip
                ];

                $data[] = $el;
                if ($statusData['config']['status'] === $elementStatus) {
                    $currentStatus = $el;
                }
            }
        }

        usort(
            $data,
            function ($status1, $status2) {
                return $status1['status'] <=> $status2['status'];
            }
        );

        return $this->json(['data' => $data, 'current' => $currentStatus]);
    }
}
