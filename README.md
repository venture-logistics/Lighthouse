## Overview

Lighthouse is a self-hosted accounting and bookkeeping application built for UK sole traders and small businesses. It provides everything needed to manage your finances in one place — from raising invoices and categorising bank transactions to submitting VAT returns directly to HMRC via Making Tax Digital (MTD).

Designed to be simple, practical, and UK-compliant, Lighthouse avoids the complexity and subscription costs of commercial software while giving you full ownership of your financial data.

## Features

### Invoicing

- Create, send, and manage invoices with customisable numbering (invoice_prefix)
- Track invoice status: draft → sent → paid → overdue → cancelled
- Line-item support with quantity, unit price, and VAT
- Configurable payment terms and default tax rates
- Custom invoice notes and payment instructions
- Company logo and branding on invoices

### Bank & Transactions

- Manage multiple bank accounts linked to your Chart of Accounts
- Import transactions from Revolut (duplicate detection via revolut_tx_id)
- Categorise transactions against your Chart of Accounts
- Track transaction status: uncategorised → categorised → reconciled
- Attach receipts to transactions
- Mark transactions as no_receipt where applicable
- Support for multi-currency with original amount tracking
- Fee and tax tracking per transaction

### Accounting

- Full double-entry bookkeeping via journal entries and journal lines
- Pre-seeded Chart of Accounts covering:
- Assets, Liabilities, Equity, Income, Expenses
- UK-specific accounts (PAYE Payable, Corporation Tax, Directors Loan)
- Generate and save Trial Balances with debit/credit totals
- Configurable accounting period start and end dates
- Support for both cash and accruals accounting methods

### HMRC & Tax

<ins>These apps are in sandbox mode!!!</ins>

- VAT return submission via HMRC's MTD API (VAT boxes 1–9)
- Support for Standard, Flat Rate, and Cash VAT schemes
- Configurable VAT quarter end month
-  MTD ITSA (Making Tax Digital for Income Tax) support
- OAuth2 token management for HMRC API (hmrc_tokens)
- Sole trader settings: UTR number, NINO, business start date

### Business Settings

- Full company profile (name, registration number, VAT number, address)
- Custom SMTP email configuration for sending invoices
- Configurable invoice prefix and auto-incrementing invoice numbers
- Bank payment details for invoice footers
- Brand colour and logo customisation

### Productivity

- Built-in diary for scheduling and notes tied to dates
- Free-form notes for general record keeping
- Login audit log tracking successful logins, failed attempts, and logouts

## Download, Support and Guides

*** Important *** : Download from the official site, [lighthousefinance.io](https://lighthousefinance.io)) - create an account and visit profile for the complete files.

### Support

DO NOT POST SUPPORT QUESTIONS HERE - use the official support forums [support.lighthousefinance.io](https://support.lighthousefinance.io))

### Guides

Minimal at present, working on it - [video.lighthousefinance.io](https://video.lighthousefinance.io))







