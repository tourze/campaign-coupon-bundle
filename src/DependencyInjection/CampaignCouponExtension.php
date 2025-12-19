<?php

declare(strict_types=1);

namespace CampaignCouponBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

/**
 * Campaign Coupon Bundle 扩展配置
 *
 * 自动加载 services.yaml 配置文件
 */
final class CampaignCouponExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
