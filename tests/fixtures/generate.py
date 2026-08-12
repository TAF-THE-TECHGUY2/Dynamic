"""
Generate tests/fixtures/totals.json.

Deliberately an independent third implementation (Python Decimal) so the
expectations are not just a recording of what app/Support/Totals.php happens
to do today. If PHP, TypeScript and this all agree, the arithmetic is right.
"""

import json
from decimal import Decimal, getcontext, ROUND_FLOOR

getcontext().prec = 60


def round_half_up(x: Decimal) -> Decimal:
    """floor(x + 1/2) — halves go towards +infinity, matching Math.round()."""
    return (x + Decimal("0.5")).to_integral_value(rounding=ROUND_FLOOR)


def quantise(value: str, places: int) -> Decimal:
    """Round an input string to the scale its database column can hold."""
    scale = Decimal(10) ** places
    return round_half_up(Decimal(value) * scale) / scale


def line_amount_cents(line: dict) -> Decimal:
    litres = line.get("litres")
    price = line.get("price_per_litre")

    if litres in (None, "") and price in (None, ""):
        return Decimal(str(line.get("amount_cents", 0)))

    litres = quantise(litres if litres not in (None, "") else "0", 2)
    price = quantise(price if price not in (None, "") else "0", 4)

    return round_half_up(litres * price * 100)


def compute(lines, vat_mode: str, vat_rate: str):
    rate = quantise(vat_rate, 4)

    amounts = [line_amount_cents(line) for line in lines]
    total_sum = sum(amounts, Decimal(0))

    if vat_mode == "none":
        subtotal, vat, total = total_sum, Decimal(0), total_sum
    elif vat_mode == "exclusive":
        subtotal = total_sum
        vat = round_half_up(total_sum * rate)
        total = subtotal + vat
    elif vat_mode == "inclusive":
        total = total_sum
        subtotal = round_half_up(total / (1 + rate))
        vat = total - subtotal
    else:
        raise ValueError(vat_mode)

    return {
        "subtotal_cents": int(subtotal),
        "vat_cents": int(vat),
        "total_cents": int(total),
        "line_amounts_cents": [int(a) for a in amounts],
    }


CASES = [
    # ---- the two cases quoted verbatim in the build brief -----------------
    ("inclusive — 12000L at 23.70",
     [{"litres": "12000", "price_per_litre": "23.7000"}], "inclusive", "0.15"),

    ("none — 3500L at 23.50 (matches existing paper quote)",
     [{"litres": "3500", "price_per_litre": "23.5000"}], "none", "0.15"),

    # ---- the same load through all three VAT modes ------------------------
    ("exclusive — 3500L at 23.50",
     [{"litres": "3500", "price_per_litre": "23.5000"}], "exclusive", "0.15"),

    ("inclusive — 3500L at 23.50",
     [{"litres": "3500", "price_per_litre": "23.5000"}], "inclusive", "0.15"),

    # ---- fractional litres ------------------------------------------------
    ("inclusive — fractional litres 1234.56 at 23.7000",
     [{"litres": "1234.56", "price_per_litre": "23.7000"}], "inclusive", "0.15"),

    ("none — fractional litres 987.65 at 22.1450",
     [{"litres": "987.65", "price_per_litre": "22.1450"}], "none", "0.15"),

    # ---- a zero line ------------------------------------------------------
    ("none — zero litres",
     [{"litres": "0", "price_per_litre": "23.7000"}], "none", "0.15"),

    ("inclusive — zero-priced delivery line alongside a real load",
     [{"litres": "12000", "price_per_litre": "23.7000"},
      {"litres": None, "price_per_litre": None, "amount_cents": 0}], "inclusive", "0.15"),

    # ---- multiple lines ---------------------------------------------------
    ("inclusive — two grades on one document",
     [{"litres": "12000", "price_per_litre": "23.7000"},
      {"litres": "6500", "price_per_litre": "23.2000"}], "inclusive", "0.15"),

    ("exclusive — three lines including a flat delivery charge",
     [{"litres": "9000", "price_per_litre": "20.6087"},
      {"litres": "1500", "price_per_litre": "20.1500"},
      {"litres": None, "price_per_litre": None, "amount_cents": 85000}], "exclusive", "0.15"),

    # ---- amounts landing exactly on a half cent ---------------------------
    ("inclusive — line lands on an exact half cent (1000.05L at 23.7000)",
     [{"litres": "1000.05", "price_per_litre": "23.7000"}], "inclusive", "0.15"),

    ("none — line lands on an exact half cent (1.00L at 0.0050)",
     [{"litres": "1.00", "price_per_litre": "0.0050"}], "none", "0.15"),

    ("none — three half-cent lines each round up before summing",
     [{"litres": "1.00", "price_per_litre": "0.0050"},
      {"litres": "1.00", "price_per_litre": "0.0050"},
      {"litres": "1.00", "price_per_litre": "0.0050"}], "none", "0.15"),

    ("exclusive — VAT itself lands on an exact half cent",
     [{"litres": "1.00", "price_per_litre": "0.1000"}], "exclusive", "0.15"),

    # 8.7 x 1.15 x 100 is exactly 1000.5, but in IEEE-754 it is 1000.4999999999999.
    # Both PHP's round() and JavaScript's Math.round() return 1000 for it. Exact
    # arithmetic returns 1001. This case is here to fail loudly if either
    # implementation ever regresses to floats.
    ("none — 8.7L at 1.1500 lands on a half cent a float would lose",
     [{"litres": "8.70", "price_per_litre": "1.1500"}], "none", "0.15"),

    # ---- a very large order ----------------------------------------------
    ("inclusive — very large order, 999999.99L at 25.0000",
     [{"litres": "999999.99", "price_per_litre": "25.0000"}], "inclusive", "0.15"),

    ("exclusive — very large order, 999999.99L at 25.0000",
     [{"litres": "999999.99", "price_per_litre": "25.0000"}], "exclusive", "0.15"),

    # ---- input precision beyond what the columns hold ---------------------
    ("inclusive — price carrying more than four decimals is rounded half up",
     [{"litres": "10000", "price_per_litre": "23.70005"}], "inclusive", "0.15"),

    ("none — litres carrying more than two decimals is rounded half up",
     [{"litres": "1000.005", "price_per_litre": "23.0000"}], "none", "0.15"),

    # ---- other VAT rates --------------------------------------------------
    ("exclusive — legacy 14% rate",
     [{"litres": "5000", "price_per_litre": "18.5000"}], "exclusive", "0.14"),

    ("exclusive — zero rate behaves like no VAT",
     [{"litres": "5000", "price_per_litre": "18.5000"}], "exclusive", "0.0000"),

    # ---- credits ----------------------------------------------------------
    ("none — a negative credit line",
     [{"litres": "12000", "price_per_litre": "23.7000"},
      {"litres": None, "price_per_litre": None, "amount_cents": -50000}], "none", "0.15"),

    ("inclusive — a negative line landing on a half cent rounds towards +infinity",
     [{"litres": "-1.00", "price_per_litre": "0.0050"}], "inclusive", "0.15"),

    # ---- degenerate input -------------------------------------------------
    ("none — no lines at all",
     [], "none", "0.15"),

    ("inclusive — no lines at all",
     [], "inclusive", "0.15"),
]


fixtures = []

for name, lines, vat_mode, vat_rate in CASES:
    fixtures.append({
        "name": name,
        "lines": lines,
        "vat_mode": vat_mode,
        "vat_rate": vat_rate,
        "expect": compute(lines, vat_mode, vat_rate),
    })

path = "/Users/tafsfolder/Documents/DynamicS/tests/fixtures/totals.json"

with open(path, "w") as handle:
    json.dump(fixtures, handle, indent=2)
    handle.write("\n")

print(f"{len(fixtures)} cases written to {path}\n")

for f in fixtures:
    e = f["expect"]
    print(f"{f['name']:<70} sub={e['subtotal_cents']:>12}  vat={e['vat_cents']:>10}  tot={e['total_cents']:>12}")
