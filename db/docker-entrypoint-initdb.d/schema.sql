BEGIN;

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Drop in dependency-safe order
DROP TABLE IF EXISTS reimbursements CASCADE;
DROP TABLE IF EXISTS transactions CASCADE;
DROP TABLE IF EXISTS pdf_statements CASCADE;
DROP TABLE IF EXISTS invoices_out CASCADE;
DROP TABLE IF EXISTS invoices_in CASCADE;
DROP TABLE IF EXISTS timesheets CASCADE;
DROP TABLE IF EXISTS employee_contracts CASCADE;
DROP TABLE IF EXISTS contracts CASCADE;
DROP TABLE IF EXISTS supplier_contracts CASCADE;
DROP TABLE IF EXISTS amortizations CASCADE;
DROP TABLE IF EXISTS holidays CASCADE;
DROP TABLE IF EXISTS properties CASCADE;
DROP TABLE IF EXISTS banks CASCADE;
DROP TABLE IF EXISTS employees CASCADE;
DROP TABLE IF EXISTS customers CASCADE;

-- ========= ROOT TABLES =========
CREATE TABLE customers (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  url TEXT,
  folder TEXT,
  file_name TEXT,
  customer TEXT NOT NULL,
  customer_address_1 TEXT,
  customer_address_2 TEXT,
  customer_vat TEXT,
  customer_email TEXT,
  customer_timesheet_email TEXT,
  customer_invoice_email TEXT,
  customer_domains TEXT,
  updated_date TIMESTAMP
);

CREATE TABLE employees (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  url TEXT,
  folder TEXT,
  file_name TEXT,
  employee TEXT NOT NULL,
  employee_address_1 TEXT,
  employee_address_2 TEXT,
  employee_email TEXT,
  salary NUMERIC(18,2) DEFAULT 0,
  ccss_contribution NUMERIC(10,4) DEFAULT 0,
  ccss_monthly NUMERIC(18,2) DEFAULT 0,
  ccss_in_period NUMERIC(18,2) DEFAULT 0,
  signed_date DATE,
  end_date DATE,
  updated_date TIMESTAMP
);

CREATE TABLE banks (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  url TEXT,
  folder TEXT,
  file_name TEXT,
  bank TEXT NOT NULL,
  iban TEXT,
  accounting TEXT,
  address TEXT,
  bic TEXT,
  beneficiary TEXT,
  created_date TIMESTAMP,
  updated_date TIMESTAMP
);

-- ========= DEPENDENTS =========
CREATE TABLE contracts (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  url TEXT,
  folder TEXT,
  file_name TEXT,
  contract_start_date DATE,
  contract_end_date DATE,
  contract_signed_date DATE,
  contract_last_renewal_date DATE,
  contract_payment_term INTEGER,
  contract_currency TEXT,
  customer_uuid TEXT NOT NULL REFERENCES customers(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  updated_date TIMESTAMP,
  created_date TIMESTAMP
);

CREATE TABLE employee_contracts (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  contract_uuid TEXT NOT NULL REFERENCES contracts(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  employee_uuid TEXT NOT NULL REFERENCES employees(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  employee_contract_start_date DATE,
  employee_contract_end_date DATE,
  contract_work_day_price NUMERIC(18,2) DEFAULT 0,
  contract_travel_day_price NUMERIC(18,2) DEFAULT 0,
  updated_date TIMESTAMP
);

CREATE TABLE invoices_in (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  file_name TEXT,
  folder TEXT,
  url TEXT,
  invoice_in_number TEXT,
  invoice_in_date DATE,
  invoice_in_merchant_country TEXT,
  invoice_in_category TEXT,
  invoice_in_merchant TEXT,
  invoice_in_due_date DATE,
  invoice_in_period_start_date DATE,
  invoice_in_period_end_date DATE,
  invoice_in_currency TEXT,
  invoice_in_original_amount NUMERIC(18,2) DEFAULT 0,
  invoice_in_original_vat NUMERIC(18,2) DEFAULT 0,
  invoice_in_eur_amount NUMERIC(18,2) DEFAULT 0,
  invoice_in_eur_vat NUMERIC(18,2) DEFAULT 0,
  invoice_in_eur_net NUMERIC(18,2) DEFAULT 0,
  updated_date TIMESTAMP,
  created_date TIMESTAMP
);


CREATE TABLE invoices_out (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  folder TEXT,
  file_name TEXT,
  url TEXT,
  invoice_out_number TEXT,
  invoice_out_date DATE,
  invoice_out_due_date DATE,
  invoice_out_period_start_date DATE,
  invoice_out_days_worked NUMERIC(10,2) DEFAULT 0,
  invoice_out_days_travel NUMERIC(10,2) DEFAULT 0,
  invoice_out_eur_work_amount NUMERIC(18,2) DEFAULT 0,
  invoice_out_eur_travel_amount NUMERIC(18,2) DEFAULT 0,
  invoice_out_eur_net_amount NUMERIC(18,2) DEFAULT 0,
  invoice_out_eur_vat NUMERIC(18,2) DEFAULT 0,
  invoice_out_eur_total_amount NUMERIC(18,2) DEFAULT 0,
  contract_uuid TEXT REFERENCES contracts(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  bank_uuid TEXT REFERENCES banks(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  updated_date TIMESTAMP,
  created_date TIMESTAMP
);

CREATE TABLE pdf_statements (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  url TEXT,
  folder TEXT,
  file_name TEXT,
  bank_uuid TEXT REFERENCES banks(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  pdf_statement_period DATE,
  pdf_statement_total_debit NUMERIC(18,2) DEFAULT 0,
  pdf_statement_total_credit NUMERIC(18,2) DEFAULT 0,
  pdf_statement_new_balance NUMERIC(18,2) DEFAULT 0,
  transaction_date_ref_balance DATE,
  updated_date TIMESTAMP,
  created_date TIMESTAMP
);

CREATE TABLE transactions (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  transaction_date DATE,
  transaction_description TEXT,
  transaction_debit_amount NUMERIC(18,2) DEFAULT 0,
  transaction_credit_amount NUMERIC(18,2) DEFAULT 0,
  transaction_currency TEXT,
  bank_uuid TEXT REFERENCES banks(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  invoice_uuid TEXT REFERENCES invoices_in(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  invoice_out_uuid TEXT REFERENCES invoices_out(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  url TEXT,
  folder TEXT,
  file_name TEXT,
  updated_date TIMESTAMP,
  created_date TIMESTAMP,
  CONSTRAINT transactions_at_most_one_invoice_ref_ck
    CHECK (NOT (invoice_uuid IS NOT NULL AND invoice_out_uuid IS NOT NULL))
);
CREATE INDEX IF NOT EXISTS idx_transactions_bank_uuid ON transactions(bank_uuid);
CREATE INDEX IF NOT EXISTS idx_transactions_invoice_uuid ON transactions(invoice_uuid);
CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(transaction_date);
CREATE INDEX IF NOT EXISTS idx_transactions_invoice_out_uuid ON transactions(invoice_out_uuid);

CREATE TABLE reimbursements (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  reimbursement_invoice_in_uuid TEXT REFERENCES invoices_in(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  -- Note: this FK column is intentionally nullable; most rows will not have a linked transaction
  reimbursement_transaction_uuid TEXT REFERENCES transactions(uuid) ON UPDATE CASCADE ON DELETE SET NULL DEFERRABLE INITIALLY DEFERRED,
  updated_date TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_reimbursements_transaction_uuid ON reimbursements(reimbursement_transaction_uuid) WHERE reimbursement_transaction_uuid IS NOT NULL;

CREATE TABLE timesheets (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  url TEXT,
  folder TEXT,
  file_name TEXT,
  timesheet_period_start_date DATE,
  customer_employee_contract_uuid TEXT REFERENCES employee_contracts(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  bank_uuid TEXT REFERENCES banks(uuid) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
  timesheet_date DATE,
  timesheet_hours_worked NUMERIC(10,2) DEFAULT 0,
  timesheet_days_travel NUMERIC(10,2) DEFAULT 0,
  timesheet_approval_date DATE,
  timesheet_approval_from TEXT,
  timesheet_approval_message_id TEXT,
  updated_date TIMESTAMP,
  created_date TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_timesheets_cec_uuid ON timesheets(customer_employee_contract_uuid);
CREATE INDEX IF NOT EXISTS idx_timesheets_bank_uuid ON timesheets(bank_uuid);
CREATE INDEX IF NOT EXISTS idx_timesheets_date ON timesheets(timesheet_date);

-- ========= INDEPENDENT =========
CREATE TABLE holidays (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  public_holiday TEXT NOT NULL,
  public_holiday_date DATE,
  weekday INTEGER,
  updated_date TIMESTAMP
);

CREATE TABLE amortizations (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  url TEXT,
  folder TEXT,
  file_name TEXT,
  purchase_date DATE,
  description TEXT,
  asset_account TEXT,
  amount_net NUMERIC(18,2) DEFAULT 0,
  amort_per_period TEXT,
  num_periods INTEGER,
  months_1st_period INTEGER,
  updated_date TIMESTAMP,
  created_date TIMESTAMP
);

CREATE TABLE supplier_contracts (
  uuid TEXT PRIMARY KEY,
  id TEXT,
  file_name TEXT,
  folder TEXT,
  url TEXT,
  supplier_name TEXT,
  supplier_category TEXT,
  supplier_contract_start_date DATE,
  supplier_contract_end_date DATE,
  supplier_contract_payment_frequency TEXT,
  supplier_contract_duration_in_frequency_units INTEGER,
  supplier_contract_gross_budget NUMERIC(18,2) DEFAULT 0,
  supplier_contract_tax_net_budget NUMERIC(18,2) DEFAULT 0,
  updated_date TIMESTAMP,
  created_date TIMESTAMP
);

CREATE TABLE properties (
  key TEXT PRIMARY KEY,
  value TEXT,
  updated_date TIMESTAMP
);
COMMIT;