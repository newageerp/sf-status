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
class StatusController extends OaBaseController {

    /**
     * @Route(path="/element-status", methods={"POST"})
     * @param Request $request
     * @return Response
     */
    public function getElementStatus(Request $request)
    {
        $request = $this->transformJsonBody($request);

        $schema = $request->get('schema');
        $type = $request->get('type');
        $elementId = $request->get('id');

        $statusGetter = 'get'.ucfirst($type);

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
                $statusDisableScope = 'cant-status-' . $status;

                $el = [
                    'text' => $statusData['config']['text'],
                    'status' => $statusData['config']['status'],
                    'badgeVariant' => isset($statusData['config']['badgeVariant']) ? $statusData['config']['badgeVariant'] : '',
                    'disabled' => in_array($statusDisableScope, $scopes),
                    'active' => $statusData['config']['status'] === $elementStatus,
                ];

                $data[] = $el;
                if ($statusData['config']['status'] === $elementStatus) {
                    $currentStatus = $el;
                }
            }
        }


        return $this->json(['data' => $data, 'current' => $currentStatus]);
    }
}