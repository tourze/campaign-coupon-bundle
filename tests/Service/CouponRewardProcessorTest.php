<?php

declare(strict_types=1);

namespace CampaignCouponBundle\Tests\Service;

use CampaignBundle\Entity\Award;
use CampaignBundle\Entity\Campaign;
use CampaignBundle\Entity\Reward;
use CampaignBundle\Enum\AwardType;
use CampaignBundle\Exception\InsufficientStockException;
use CampaignCouponBundle\Service\CouponRewardProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CouponCoreBundle\Entity\Coupon;
use Tourze\CouponCoreBundle\Exception\CouponNotFoundException;
use Tourze\CouponCoreBundle\Service\CouponService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CouponRewardProcessor::class)]
#[RunTestsInSeparateProcesses]
final class CouponRewardProcessorTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
    }

    public function testSupportsCouponType(): void
    {
        $processor = self::getService(CouponRewardProcessor::class);

        $this->assertTrue($processor->supports(AwardType::COUPON));
    }

    public function testDoesNotSupportOtherTypes(): void
    {
        $processor = self::getService(CouponRewardProcessor::class);

        $this->assertFalse($processor->supports(AwardType::CREDIT));
        $this->assertFalse($processor->supports(AwardType::SPU_QUALIFICATION));
        $this->assertFalse($processor->supports(AwardType::SKU_QUALIFICATION));
        $this->assertFalse($processor->supports(AwardType::COUPON_LOCAL));
    }

    public function testGetPriorityReturnsZero(): void
    {
        $processor = self::getService(CouponRewardProcessor::class);

        $this->assertSame(0, $processor->getPriority());
    }

    public function testProcessSuccessfullyWithValidCoupon(): void
    {
        $processor = self::getService(CouponRewardProcessor::class);
        $couponService = self::getService(CouponService::class);

        // 创建测试优惠券
        $coupon = $this->createTestCoupon();
        // 为优惠券预创建库存
        $couponService->createOneCode($coupon);

        // 创建测试活动
        $campaign = $this->createTestCampaign();

        // 创建 Award
        $award = new Award();
        $award->setCampaign($campaign);
        $award->setType(AwardType::COUPON);
        $award->setValue((string) $coupon->getId());
        $campaign->addAward($award);

        // 创建 Reward（带初始 sn，因为数据库约束要求非空）
        $reward = new Reward();
        $reward->setCampaign($campaign);
        $reward->setAward($award);
        $reward->setType(AwardType::COUPON);
        $reward->setValue((string) $coupon->getId());
        $reward->setSn('TEMP_' . uniqid());  // 临时 sn，处理器会覆盖

        // 持久化实体
        $em = self::getEntityManager();
        $em->persist($award);
        $em->persist($reward);
        $em->flush();

        // 创建用户
        $user = $this->createNormalUser('test@example.com', 'password');

        // 执行处理
        $processor->process($user, $award, $reward);

        // 验证流水号已设置（不再是临时值）
        $sn = $reward->getSn();
        $this->assertNotNull($sn);
        $this->assertStringStartsNotWith('TEMP_', $sn);
    }

    public function testProcessThrowsExceptionWhenCouponNotFound(): void
    {
        $processor = self::getService(CouponRewardProcessor::class);

        // 创建测试活动
        $campaign = $this->createTestCampaign();

        // 创建 Award，使用不存在的优惠券ID
        $award = new Award();
        $award->setCampaign($campaign);
        $award->setType(AwardType::COUPON);
        $award->setValue('INVALID_COUPON_ID');
        $campaign->addAward($award);

        // 创建 Reward（带初始 sn）
        $reward = new Reward();
        $reward->setCampaign($campaign);
        $reward->setAward($award);
        $reward->setType(AwardType::COUPON);
        $reward->setValue('INVALID_COUPON_ID');
        $reward->setSn('TEMP_' . uniqid());

        // 持久化实体
        $em = self::getEntityManager();
        $em->persist($award);
        $em->persist($reward);
        $em->flush();

        // 创建用户
        $user = $this->createNormalUser('test@example.com', 'password');

        $this->expectException(CouponNotFoundException::class);
        $processor->process($user, $award, $reward);
    }

    public function testProcessThrowsExceptionWhenNoStock(): void
    {
        $processor = self::getService(CouponRewardProcessor::class);

        // 创建测试优惠券但不创建库存
        $coupon = $this->createTestCoupon();
        // 不创建任何优惠券码，保持库存为0

        // 创建测试活动
        $campaign = $this->createTestCampaign();

        // 创建 Award
        $award = new Award();
        $award->setCampaign($campaign);
        $award->setType(AwardType::COUPON);
        $award->setValue((string) $coupon->getId());
        $campaign->addAward($award);

        // 创建 Reward（带初始 sn）
        $reward = new Reward();
        $reward->setCampaign($campaign);
        $reward->setAward($award);
        $reward->setType(AwardType::COUPON);
        $reward->setValue((string) $coupon->getId());
        $reward->setSn('TEMP_' . uniqid());

        // 持久化实体
        $em = self::getEntityManager();
        $em->persist($award);
        $em->persist($reward);
        $em->flush();

        // 创建用户
        $user = $this->createNormalUser('test@example.com', 'password');

        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('优惠券库存不足');
        $processor->process($user, $award, $reward);
    }

    public function testProcessWithCouponSn(): void
    {
        $processor = self::getService(CouponRewardProcessor::class);
        $couponService = self::getService(CouponService::class);

        // 创建测试优惠券
        $coupon = $this->createTestCoupon();
        // 为优惠券预创建库存
        $couponService->createOneCode($coupon);

        // 创建测试活动
        $campaign = $this->createTestCampaign();

        // 创建 Award，使用优惠券SN而不是ID
        $award = new Award();
        $award->setCampaign($campaign);
        $award->setType(AwardType::COUPON);
        $couponSn = $coupon->getSn();
        $this->assertNotNull($couponSn);
        $award->setValue($couponSn);
        $campaign->addAward($award);

        // 创建 Reward（带初始 sn）
        $reward = new Reward();
        $reward->setCampaign($campaign);
        $reward->setAward($award);
        $reward->setType(AwardType::COUPON);
        $reward->setValue($couponSn);
        $reward->setSn('TEMP_' . uniqid());

        // 持久化实体
        $em = self::getEntityManager();
        $em->persist($award);
        $em->persist($reward);
        $em->flush();

        // 创建用户
        $user = $this->createNormalUser('test@example.com', 'password');

        // 执行处理
        $processor->process($user, $award, $reward);

        // 验证流水号已更新（不再是临时值）
        $sn = $reward->getSn();
        $this->assertNotNull($sn);
        $this->assertStringStartsNotWith('TEMP_', $sn);
    }

    public function testProcessServiceCanBeRetrievedFromContainer(): void
    {
        $processor = self::getService(CouponRewardProcessor::class);
        $this->assertInstanceOf(CouponRewardProcessor::class, $processor);
    }

    private function createTestCoupon(): Coupon
    {
        $coupon = new Coupon();
        $coupon->setName('测试优惠券');
        $coupon->setSn('TEST_COUPON_' . uniqid());
        $coupon->setValid(true);
        $coupon->setExpireDay(30);

        $em = self::getEntityManager();
        $em->persist($coupon);
        $em->flush();

        return $coupon;
    }

    private function createTestCampaign(): Campaign
    {
        $campaign = new Campaign();
        $campaign->setName('测试活动');
        $campaign->setCode('TEST_' . uniqid());
        $campaign->setValid(true);
        $campaign->setStartTime(new \DateTimeImmutable());
        $campaign->setEndTime(new \DateTimeImmutable('+30 days'));

        $em = self::getEntityManager();
        $em->persist($campaign);
        $em->flush();

        return $campaign;
    }
}
