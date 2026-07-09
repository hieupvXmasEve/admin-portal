<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

/**
 * How Finance obtains an amount for an obligation/entitlement type.
 *
 * Only pricing *rules* (amounts, effective dates) live in the DB catalog;
 * this enum is code-owned behaviour wiring (ADR-0027).
 */
enum PricingStrategy: string
{
    /** Fixed amount from finance_pricing_catalog_items (no fact match required). */
    case CatalogFixed = 'catalog_fixed';

    /** Catalog lookup with facts_match (program/term/level, etc.). */
    case CatalogFacts = 'catalog_facts';

    /** Generator/batch supplies amount until the type is fully on catalog. */
    case GeneratorAmount = 'generator_amount';

    /** Staff enters amount at create time (manual / adjustment). */
    case StaffSupplied = 'staff_supplied';

    /** Amount computed from policy (scholarship %, voucher rules, EGC retake). */
    case PolicyComputed = 'policy_computed';

    /** No independent pricing (e.g. pure ledger conversion / not yet priced). */
    case NotApplicable = 'not_applicable';
}
