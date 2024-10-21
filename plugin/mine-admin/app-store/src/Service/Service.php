<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */

namespace Plugin\MineAdmin\AppStore\Service;

use App\Exception\BusinessException;
use App\Http\Common\ResultCode;
use Hyperf\HttpMessage\Upload\UploadedFile;
use Mine\AppStore\Plugin;
use Mine\AppStore\Service\Impl\AppStoreServiceImpl;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Service
{
    public function download(array $params): bool
    {
        if (empty($params['space']) || empty($params['identifier']) || empty($params['version'])) {
            $this->throwParamsFail();
        }

        $service = make(AppStoreServiceImpl::class);

        if (! is_dir(BASE_PATH . '/plugin/' . $params['space'] . '/' . $params['identifier'])) {
            $result = $service->download($params['space'], $params['identifier'], $params['version']);
            if (! $result) {
                $this->throwDownloadFail();
            }
        }

        return true;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function install(array $params): bool
    {
        if (empty($params['space']) || empty($params['identifier']) || empty($params['version'])) {
            $this->throwParamsFail();
        }

        $path = BASE_PATH . '/plugin/' . $params['space'] . '/' . $params['identifier'];

        if (file_exists($path . '/install.lock')) {
            $this->throwAppInstalled();
        }

        $pluginName = $params['space'] . '/' . $params['identifier'];
        try {
            Plugin::forceRefreshJsonPath();
            Plugin::install($pluginName);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage());
        }

        return true;
    }

    public function unInstall(array $params): bool
    {
        if (empty($params['space']) || empty($params['identifier']) || empty($params['version'])) {
            $this->throwParamsFail();
        }

        $path = BASE_PATH . '/plugin/' . $params['space'] . '/' . $params['identifier'];

        if (! file_exists($path . '/install.lock')) {
            $this->throwAppNoInstall();
        }

        $pluginName = $params['space'] . '/' . $params['identifier'];
        try {
            Plugin::forceRefreshJsonPath();
            Plugin::uninstall($pluginName);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage());
        }
        return true;
    }

    public function getLocalAppInstallList(): array
    {
        $list = Plugin::getPluginJsonPaths();

        $items = [];
        foreach ($list as $splFileInfo) {
            $info = Plugin::read($splFileInfo->getRelativePath());
            if (! empty($info)) {
                $items[$info['name']] = [
                    'status' => $info['status'],
                    'version' => $info['version'],
                ];
            }
        }
        return $items;
    }

    public function uploadLocalApp(UploadedFile $file): bool
    {
        try {
            $runtimePath = BASE_PATH . '/runtime/' . uniqid('mineApp', true) . '.zip';
            $file->moveTo($runtimePath);
            $zip = new \ZipArchive();
            $zip->open($runtimePath);
            if ($zip->status !== \ZipArchive::ER_OK) {
                throw new \RuntimeException('Failed to open the zip file');
            }
            $json = json_decode(
                $zip->getFromName('mine.json'),
                true,
                512,
                \JSON_THROW_ON_ERROR
            );
            $zip->extractTo(Plugin::PLUGIN_PATH . '/' . $json['name']);
            $zip->close();
            Plugin::forceRefreshJsonPath();
            Plugin::install($json['name']);
            @unlink($runtimePath);
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage());
        }
        return true;
    }

    protected function throwParamsFail()
    {
        throw new BusinessException(ResultCode::FAIL, trans('app-store.params_fail'));
    }

    protected function throwDownloadFail()
    {
        throw new BusinessException(ResultCode::FAIL, trans('app-store.download_fail'));
    }

    protected function throwAppInstalled()
    {
        throw new BusinessException(ResultCode::FAIL, trans('app-store.app_installed'));
    }

    protected function throwAppNoInstall()
    {
        throw new BusinessException(ResultCode::FAIL, trans('app-store.app_not_installed'));
    }
}
