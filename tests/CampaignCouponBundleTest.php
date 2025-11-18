<?php

declare(strict_types=1);

namespace CampaignCouponBundle\Tests;

use CampaignCouponBundle\CampaignCouponBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(CampaignCouponBundle::class)]
#[RunTestsInSeparateProcesses]
final class CampaignCouponBundleTest extends AbstractBundleTestCase
{
}
