<?php

declare(strict_types=1);

namespace CampaignCouponBundle\Service;

use CampaignBundle\Contract\RewardProcessorInterface;
use CampaignBundle\Entity\Award;
use CampaignBundle\Entity\Reward;
use CampaignBundle\Enum\AwardType;
use CampaignBundle\Exception\InsufficientStockException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\Service\CouponService;

/**
 * 优惠券奖励处理器
 *
 * 负责处理活动中优惠券类型的奖励发放。
 *
 * 处理流程：
 * 1. 从 Award 值中获取优惠券标识
 * 2. 检查优惠券库存
 * 3. 发送优惠券给用户
 * 4. 将优惠券流水号记录到 Reward 的 sn 字段
 *
 * @see \CampaignBundle\Enum\AwardType::COUPON
 */
#[WithMonologChannel(channel: 'campaign_coupon')]
readonly class CouponRewardProcessor implements RewardProcessorInterface
{
    public function __construct(
        private CouponService $couponService,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(AwardType $type): bool
    {
        return AwardType::COUPON === $type;
    }

    public function process(UserInterface $user, Award $award, Reward $reward): void
    {
        $couponIdentifier = $award->getValue();

        try {
            // 1. 检测优惠券
            $coupon = $this->couponService->detectCoupon($couponIdentifier);

            // 2. 检查库存
            $stock = $this->couponService->getCouponValidStock($coupon);
            if ($stock <= 0) {
                throw new InsufficientStockException('优惠券库存不足');
            }

            // 3. 发送优惠券
            $code = $this->couponService->sendCode($user, $coupon);

            // 4. 记录流水号
            if (null !== $sn = $code->getSn()) {
                $reward->setSn($sn);
            }

            $this->logger->info('Coupon reward sent successfully', [
                'coupon_identifier' => $couponIdentifier,
                'user_id' => method_exists($user, 'getId') ? $user->getId() : 'unknown',
                'code_sn' => $sn ?? null,
                'campaign_id' => $award->getCampaign()->getId(),
                'award_id' => $award->getId(),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send coupon reward', [
                'coupon_identifier' => $couponIdentifier,
                'user_id' => method_exists($user, 'getId') ? $user->getId() : 'unknown',
                'campaign_id' => $award->getCampaign()->getId(),
                'award_id' => $award->getId(),
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // 重新抛出异常，让上层处理
            throw $exception;
        }
    }

    public function getPriority(): int
    {
        return 0;
    }
}
