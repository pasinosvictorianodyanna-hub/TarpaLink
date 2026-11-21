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
     *   lines: array<int, array{label: string, amount: int}>,
     *   subtotal: int,
     *   discount: int,
     *   total: int,
     *   currency: string,
     *   matrix_version: int
     * }
     */
    public function quote(Shop $shop, array $spec): array;
}
```

## Rules

- **All money is integer centavos.** `₱1,967.00` is `196700`. No floats, no decimals,
  no `round()` after the fact. Formatting happens once, in the view.
- **The shop is always passed in.** Prices are per-shop, never global. A shop that
  has not configured a service returns a `ServiceUnavailable` exception, not a default
  price.
- **`matrix_version` is returned** and stored on the job line. When a shop changes a
  tier next month, old jobs must still show the price the customer agreed to.
- **The total is the shop's price.** The platform does not add a fee on top of it.
  Commission is deducted from the shop's payout, never charged to the customer.
- **Server is authoritative.** The Vue builder may call a preview endpoint to feel
  instant, but the job is created from a fresh `quote()` call. If they differ, the
  server number wins and the client is told.

## Example

```
Tarpaulin, 4ft x 8ft, qty 3, eyelets x8, hemming

lines:
  Tarpaulin 4x8 (size tier B)    3 × ₱620   = ₱1,860
  Eyelets (8 pcs)                8 × ₱10    =    ₱80
  Hemming (16 ft)               16 × ₱15    =   ₱240
  Quantity discount (3+)                          -₱213
  -------------------------------------------------------
  total                                          ₱1,967
```

`matrix_version: 7` — recorded on the job line so the receipt never changes retroactively.
