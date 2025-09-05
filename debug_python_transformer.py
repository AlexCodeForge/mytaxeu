#!/usr/bin/env python3

import pandas as pd
import numpy as np

# Load CSV data
df = pd.read_csv('docs/transformer/input-output/ENERO 2023.csv')

# Initialize clasificaciones dict
clasificaciones = {
    "Ventas locales al consumidor final - B2C y B2B (EUR)": [],
    "Ventas locales SIN IVA (EUR)": [],
    "Ventas Intracomunitarias de bienes - B2B (EUR)": [],
    "Ventanilla Única - OSS esquema europeo (EUR)": [],
    "Ventanilla Única - IOSS esquema de importación (EUR)": [],
    "IVA recaudado y remitido por Amazon Marketplace (EUR)": [],
    "Compras a Amazon (EUR)": [],
    "Exportaciones (EUR)": []
}

# Define columns and exchange rates
exchange_rates_to_euro = {
    "PLN": 0.23,
    "EUR": 1.0,
    "SEK": 0.087
}

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

# Filter for B2C/B2B only
for index, row in df.iterrows():
    reporting_scheme = row.get('TAX_REPORTING_SCHEME')
    tax_responsibility = row.get('TAX_COLLECTION_RESPONSIBILITY')

    if (reporting_scheme in ["REGULAR", "UK_VOEC-DOMESTIC"] and tax_responsibility == "SELLER"):
        clasificaciones["Ventas locales al consumidor final - B2C y B2B (EUR)"].append(row.to_dict())

print(f"B2C/B2B transactions collected: {len(clasificaciones['Ventas locales al consumidor final - B2C y B2B (EUR)'])}")

# Convert to DataFrame
key = "Ventas locales al consumidor final - B2C y B2B (EUR)"
clasificaciones[key] = pd.DataFrame(clasificaciones[key])

print(f"DataFrame created with {len(clasificaciones[key])} rows")

# Convert numeric columns
for column in columns_to_sum:
    if column in clasificaciones[key].columns:
        clasificaciones[key][column] = pd.to_numeric(clasificaciones[key][column], errors='coerce').fillna(0)

# Filter for POLAND only for debugging
poland_before = clasificaciones[key][clasificaciones[key]['TAXABLE_JURISDICTION'] == 'POLAND'].copy()
print(f"POLAND transactions before conversion: {len(poland_before)}")

# Check currency breakdown before conversion
currency_counts = poland_before['TRANSACTION_CURRENCY_CODE'].value_counts()
print(f"Currency breakdown before conversion: {currency_counts.to_dict()}")

# Show IVA amounts before conversion
poland_pln = poland_before[poland_before['TRANSACTION_CURRENCY_CODE'] == 'PLN']
poland_eur = poland_before[poland_before['TRANSACTION_CURRENCY_CODE'] == 'EUR']
print(f"PLN transactions IVA total before: {poland_pln['PRICE_OF_ITEMS_VAT_AMT'].sum():.2f}")
print(f"EUR transactions IVA total before: {poland_eur['PRICE_OF_ITEMS_VAT_AMT'].sum():.2f}")

# Apply currency conversion
print(f"\nApplying currency conversion for B2C/B2B...")
conversion_count = 0
if 'TRANSACTION_CURRENCY_CODE' in clasificaciones[key].columns:
    for index, row in clasificaciones[key].iterrows():
        currency = row['TRANSACTION_CURRENCY_CODE']
        jurisdiction = row.get('TAXABLE_JURISDICTION', '')

        if currency != "EUR" and currency in exchange_rates_to_euro:
            if jurisdiction == 'POLAND':  # Only log POLAND conversions
                print(f"Converting POLAND transaction {index}: {currency} -> EUR")
                old_vat = row['PRICE_OF_ITEMS_VAT_AMT']
                conversion_count += 1

            for column in columns_to_sum:
                if column in clasificaciones[key].columns:
                    clasificaciones[key].at[index, column] = round(row[column] * exchange_rates_to_euro[currency], 2)
            clasificaciones[key].at[index, 'TRANSACTION_CURRENCY_CODE'] = "EUR"

            if jurisdiction == 'POLAND':  # Only log POLAND conversions
                new_vat = clasificaciones[key].at[index, 'PRICE_OF_ITEMS_VAT_AMT']
                print(f"  PRICE_OF_ITEMS_VAT_AMT: {old_vat} -> {new_vat}")

print(f"Total conversions applied: {conversion_count}")

# Check after conversion
poland_after = clasificaciones[key][clasificaciones[key]['TAXABLE_JURISDICTION'] == 'POLAND'].copy()
print(f"\nPOLAND transactions after conversion: {len(poland_after)}")

# Calculate IVA (€) column
clasificaciones[key]['IVA (€)'] = clasificaciones[key][[
    'PRICE_OF_ITEMS_VAT_AMT', 'PROMO_PRICE_OF_ITEMS_VAT_AMT',
    'SHIP_CHARGE_VAT_AMT', 'PROMO_SHIP_CHARGE_VAT_AMT',
    'GIFT_WRAP_VAT_AMT', 'PROMO_GIFT_WRAP_VAT_AMT'
]].sum(axis=1)

poland_final = clasificaciones[key][clasificaciones[key]['TAXABLE_JURISDICTION'] == 'POLAND'].copy()
poland_iva_total = poland_final['IVA (€)'].sum()
print(f"\nFinal POLAND IVA (€) total: {poland_iva_total:.2f}")
print(f"Expected (PHP result): 91.54")
print(f"Difference: {abs(poland_iva_total - 91.54):.2f}")

# Check individual VAT column totals for POLAND after conversion
print(f"\nPOLAND VAT column breakdown after conversion:")
for col in ['PRICE_OF_ITEMS_VAT_AMT', 'PROMO_PRICE_OF_ITEMS_VAT_AMT', 'SHIP_CHARGE_VAT_AMT', 'PROMO_SHIP_CHARGE_VAT_AMT', 'GIFT_WRAP_VAT_AMT', 'PROMO_GIFT_WRAP_VAT_AMT']:
    total = poland_final[col].sum()
    if total != 0:
        print(f"  {col}: {total:.2f}")

