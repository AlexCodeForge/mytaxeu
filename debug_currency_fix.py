#!/usr/bin/env python3

import pandas as pd
import numpy as np

# Load CSV data
df = pd.read_csv('docs/transformer/input-output/ENERO 2023.csv')

# Define exchange rates (same as Python script)
exchange_rates_to_euro = {
    "PLN": 0.23,
    "EUR": 1.0,
    "SEK": 0.087
}

# Define columns to sum (same as Python script)
columns_to_sum = [
    'COST_PRICE_OF_ITEMS',
    'PRICE_OF_ITEMS_AMT_VAT_EXCL', 'PROMO_PRICE_OF_ITEMS_AMT_VAT_EXCL',
    'TOTAL_PRICE_OF_ITEMS_AMT_VAT_EXCL',
    'SHIP_CHARGE_AMT_VAT_EXCL', 'PROMO_SHIP_CHARGE_AMT_VAT_EXCL',
    'TOTAL_SHIP_CHARGE_AMT_VAT_EXCL',
    'GIFT_WRAP_AMT_VAT_EXCL', 'PROMO_GIFT_WRAP_AMT_VAT_EXCL',
    'TOTAL_GIFT_WRAP_AMT_VAT_EXCL',
    'TOTAL_ACTIVITY_VALUE_AMT_VAT_EXCL',
    'PRICE_OF_ITEMS_VAT_RATE_PERCENT',
    'PRICE_OF_ITEMS_VAT_AMT', 'PROMO_PRICE_OF_ITEMS_VAT_AMT',
    'TOTAL_PRICE_OF_ITEMS_VAT_AMT',
    'SHIP_CHARGE_VAT_RATE_PERCENT',
    'SHIP_CHARGE_VAT_AMT', 'PROMO_SHIP_CHARGE_VAT_AMT',
    'TOTAL_SHIP_CHARGE_VAT_AMT',
    'GIFT_WRAP_VAT_RATE_PERCENT',
    'GIFT_WRAP_VAT_AMT', 'PROMO_GIFT_WRAP_VAT_AMT',
    'TOTAL_GIFT_WRAP_VAT_AMT',
    'TOTAL_ACTIVITY_VALUE_VAT_AMT',
    'PRICE_OF_ITEMS_AMT_VAT_INCL', 'PROMO_PRICE_OF_ITEMS_AMT_VAT_INCL',
    'TOTAL_PRICE_OF_ITEMS_AMT_VAT_INCL',
    'SHIP_CHARGE_AMT_VAT_INCL', 'PROMO_SHIP_CHARGE_AMT_VAT_INCL',
    'TOTAL_SHIP_CHARGE_AMT_VAT_INCL',
    'GIFT_WRAP_AMT_VAT_INCL', 'PROMO_GIFT_WRAP_AMT_VAT_INCL',
    'TOTAL_GIFT_WRAP_AMT_VAT_INCL',
    'TOTAL_ACTIVITY_VALUE_AMT_VAT_INCL',
]

# Filter for B2C/B2B classification
b2c_b2b_mask = (
    df['TAX_REPORTING_SCHEME'].isin(['REGULAR', 'UK_VOEC-DOMESTIC']) &
    (df['TAX_COLLECTION_RESPONSIBILITY'] == 'SELLER')
)

b2c_b2b_df = df[b2c_b2b_mask].copy()

# Filter for POLAND
poland_df = b2c_b2b_df[b2c_b2b_df['TAXABLE_JURISDICTION'] == 'POLAND'].copy()

print(f"Total POLAND B2C/B2B transactions: {len(poland_df)}")

# Check BEFORE conversion
print(f"\nBEFORE currency conversion:")
for currency in ['EUR', 'PLN']:
    subset = poland_df[poland_df['TRANSACTION_CURRENCY_CODE'] == currency]
    if len(subset) > 0:
        item_vat = subset['PRICE_OF_ITEMS_VAT_AMT'].fillna(0).sum()
        print(f"  {currency}: {len(subset)} transactions, PRICE_OF_ITEMS_VAT_AMT total: {item_vat:.2f}")

# Apply currency conversion (simulate Python script fix)
print(f"\nApplying currency conversion...")
for index, row in poland_df.iterrows():
    currency = row['TRANSACTION_CURRENCY_CODE']
    if currency != "EUR" and currency in exchange_rates_to_euro:
        rate = exchange_rates_to_euro[currency]
        print(f"Converting transaction {index}: {currency} -> EUR (rate: {rate})")

        # Convert specific VAT columns
        old_item_vat = poland_df.at[index, 'PRICE_OF_ITEMS_VAT_AMT']
        poland_df.at[index, 'PRICE_OF_ITEMS_VAT_AMT'] = round(old_item_vat * rate, 2)
        new_item_vat = poland_df.at[index, 'PRICE_OF_ITEMS_VAT_AMT']
        print(f"  PRICE_OF_ITEMS_VAT_AMT: {old_item_vat} -> {new_item_vat}")

        poland_df.at[index, 'TRANSACTION_CURRENCY_CODE'] = "EUR"

# Check AFTER conversion
print(f"\nAFTER currency conversion:")
poland_item_vat_total = poland_df['PRICE_OF_ITEMS_VAT_AMT'].fillna(0).sum()
print(f"  Total PRICE_OF_ITEMS_VAT_AMT: {poland_item_vat_total:.2f}")

# Calculate IVA (€) as Python script does
poland_df['IVA (€)'] = poland_df[[
    'PRICE_OF_ITEMS_VAT_AMT', 'PROMO_PRICE_OF_ITEMS_VAT_AMT',
    'SHIP_CHARGE_VAT_AMT', 'PROMO_SHIP_CHARGE_VAT_AMT',
    'GIFT_WRAP_VAT_AMT', 'PROMO_GIFT_WRAP_VAT_AMT'
]].sum(axis=1)

poland_iva_total = poland_df['IVA (€)'].sum()
print(f"\nCalculated IVA (€) total for POLAND: {poland_iva_total:.2f}")
print(f"Expected (PHP result): 91.54")
print(f"Difference: {abs(poland_iva_total - 91.54):.2f}")

