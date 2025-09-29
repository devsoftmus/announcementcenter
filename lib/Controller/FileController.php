<?php

declare(strict_types=1);

namespace OCA\AnnouncementCenter\Controller;

use OCA\AnnouncementCenter\Service\FileService;
use OCA\Done\Service\AppearanceService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * Controller for handling file requests
 */
class FileController extends OCSController
{
    /** @var FileService */
    private FileService $fileService;

    public function __construct($appName, IRequest $request, FileService $fileService)
    {
        parent::__construct($appName, $request);
        $this->fileService      = $fileService;
    }

    /**
     * Get project file
     *
     * The method is used in the file lib/base.php
     *
     * @param string $entityId
     * @param string $fileType File type
     * @param string $fileName File name
     * @return DataDownloadResponse|DataResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function getEntityFile(
        string $entityId,
        string $fileName
    ): DataDownloadResponse|DataResponse {
        $fileData = $this->fileService->getFile($entityId, $fileName);;

        if ($fileData === null) {
            return new DataResponse('', Http::STATUS_NOT_FOUND);
        }

        $response = new DataDownloadResponse(
            $fileData['content'],
            $fileData['name'],
            $fileData['mimeType']
        );

        // Cache for 1 hour
        $response->cacheFor(3600, false, true);

        return $response;
    }
}
