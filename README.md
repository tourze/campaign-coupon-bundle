# Campaign Coupon Bundle

[English](README.md) | [中文](README.zh-CN.md)

优惠券奖励扩展包，为 `campaign-bundle` 提供优惠券类型的活动奖励支持。

## 功能

- ✅ 处理 `AwardType::COUPON` 类型的活动奖励
- ✅ 自动检查优惠券库存
- ✅ 自动发送优惠券给用户
- ✅ 记录优惠券发放流水号
- ✅ 完整的日志记录

## 安装

```bash
composer require tourze/campaign-coupon-bundle
```

## 使用

### 1. 注册 Bundle

在 `config/bundles.php` 中添加：

```php
return [
    // ...
    CampaignCouponBundle\CampaignCouponBundle::class => ['all' => true],
];
```

### 2. 创建优惠券活动奖励

```php
use CampaignBundle\Entity\Campaign;
use CampaignBundle\Entity\Award;
use CampaignBundle\Enum\AwardType;

$campaign = new Campaign();
$campaign->setCode('SUMMER2024');
$campaign->setName('夏季优惠券活动');

$award = new Award();
$award->setCampaign($campaign);
$award->setEvent('join');
$award->setType(AwardType::COUPON);
$award->setValue('COUPON_CODE_001'); // 优惠券标识
$award->setPrizeQuantity(1000);

// 保存到数据库
$entityManager->persist($campaign);
$entityManager->persist($award);
$entityManager->flush();
```

### 3. 自动发放

当用户参与活动并获得奖励时，系统会自动：
1. 检测优惠券库存
2. 发送优惠券给用户
3. 记录流水号到 Reward 的 `sn` 字段

## 架构

### 核心类

- `CouponRewardProcessor`：实现 `RewardProcessorInterface`，处理优惠券发放逻辑

### 处理流程

```
CampaignService::rewardUser()
    ↓
CampaignRewardProcessorService::processRewardByType()
    ↓
RewardProcessorRegistry::getProcessor(AwardType::COUPON)
    ↓
CouponRewardProcessor::process()
    ↓
CouponService::sendCode()
```

### 依赖关系

```
campaign-coupon-bundle
├── campaign-bundle (核心活动管理)
└── coupon-core-bundle (优惠券系统)
```

## 日志

处理器会记录详细的日志信息：

**成功日志**：
```
[info] Coupon reward sent successfully
{
  "coupon_identifier": "COUPON_CODE_001",
  "user_id": 12345,
  "code_sn": "CPN20240715001",
  "campaign_id": 1,
  "award_id": 10
}
```

**失败日志**：
```
[error] Failed to send coupon reward
{
  "coupon_identifier": "COUPON_CODE_001",
  "user_id": 12345,
  "campaign_id": 1,
  "award_id": 10,
  "exception": "优惠券库存不足",
  "trace": "..."
}
```

## 异常处理

- `InsufficientStockException`：优惠券库存不足时抛出
- 其他异常会被记录并重新抛出，由上层处理

## 扩展

如果需要自定义优惠券发放逻辑，可以创建自己的处理器并设置更高的优先级：

```php
class CustomCouponRewardProcessor implements RewardProcessorInterface
{
    public function supports(AwardType $type): bool
    {
        return AwardType::COUPON === $type;
    }

    public function process(UserInterface $user, Award $award, Reward $reward): void
    {
        // 自定义逻辑
    }

    public function getPriority(): int
    {
        return 10; // 高于默认的 0
    }
}
```

## 许可证

MIT
