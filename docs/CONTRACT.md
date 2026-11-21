# Price engine contract

Both halves of the app depend on this one class. It is written once, by whoever
builds the spine, and nobody reimplements it.

`app/Services/PriceCalculator.php`

```php
final class PriceCalculator
{
    /**
     * Quote a job against a shop's own price matrix.
     *
     * @param  array{
     *   service_id: int,
     *   width_in: float,
     *   height_in: float,
     *   qty: int,
     *   options: array<int, int>
     * } $spec
     *
     * @return array{
     *   lines: array<int, array{label: string, qty: int, unit_price: int, amount: int}>,
     *   subtotal: int,
     *   discount: int,
     *   total: int,
     *   currency: string,
     *   matrix_version: int,
     *   tier: string
     * }
     */
    public function quote(Shop $shop, array $spec): array;
}
```

## Rules

- **All money is integer centavos.** `₱2,036.80` is `203680`. No floats, no decimals,
  no `round()` after the fact. Formatting happens once, in the view.
- **The shop is always passed in.** Prices are per-shop, never global. A shop that
  has not configured a service returns a `ServiceUnavailable` exception, not a default
  price. Same for a size that falls outside every tier.
- **`unit_price` means two different things depending on the service's `unit`:**
  `sqft` → price per square foot, multiplied by the actual area; `pc` → price per
  piece. Tarpaulin is `sqft`, stickers/flyers/shirts are `pc`.
- **`matrix_version` is returned** and stored on the **job** (not the line). It is a
  short fingerprint of the tier rows (`crc32 % 10000`), so it changes whenever a price
  changes and old jobs still show the price the customer agreed to.
- **The total is the shop's price.** The platform does not add a fee on top of it.
  Commission is deducted from the shop's payout, never charged to the customer.
- **The commission rate is a shop attribute**, `shops.commission_rate`, stored in
  basis points (`300` = 3%). It is never a literal in a controller or a payout query.
  Each payment row stores the commission as an absolute centavo amount, so a shop
  upgrading its plan never rewrites last month's payouts.
- **Server is authoritative.** The Vue builder may call a preview endpoint to feel
  instant, but the job is created from a fresh `quote()` call. If they differ, the
  server number wins and the client is told.

## Example

Real output from `PriceCalculator`, not an illustration. Inkwell Press, Tarpaulin
(13oz vinyl, priced per square foot), 48×96 in, qty 3, 8 eyelets, 16 ft hemming:

```
lines:
  Tarpaulin Large (≤96in)   3 × ₱608.00  = ₱1,824.00    (32 sq ft × ₱19/sq ft)
  Eyelets (8 pc)            8 × ₱10.00   =    ₱80.00
  Hemming (16 ft)          16 × ₱15.00   =   ₱240.00
  ------------------------------------------------
  subtotal                                 ₱2,144.00
  quantity discount (3+ = 5%)                -₱107.00
  ------------------------------------------------
  total                                    ₱2,036.80

matrix_version: 6612
tier: "Large (≤96in)"
```

Note what the example proves: the tier is picked by the **long edge** (96 in lands in
"Large ≤96in", not "Medium ≤48in"), the base line is `area × unit_price` rather than a
flat per-size price, and the discount is applied to the subtotal *after* the
finishing lines are added.
