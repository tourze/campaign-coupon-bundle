<?php

declare(strict_types=1);

namespace CampaignCouponBundle;

use CampaignBundle\CampaignBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\CouponCoreBundle\CouponCoreBundle;

/**
 * 活动优惠券扩展 Bundle
 *
 * 为 campaign-bundle 提供优惠券类型的奖励处理能力。
 *
 * 功能：
 * - 处理 AwardType::COUPON 类型的奖励
 * - 自动发送优惠券给用户
 * - 检查优惠券库存
 * - 记录优惠券发放流水号
 *
 * 依赖：
 * - campaign-bundle：核心活动管理
 * - coupon-core-bundle：优惠券系统
 */
class CampaignCouponBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            CampaignBundle::class => ['all' => true],
            CouponCoreBundle::class => ['all' => true],
        ];
    }
}
