CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS "customers" (
  id BIGSERIAL PRIMARY KEY
 , "url" TEXT
 , "folder" TEXT
 , "file_name" TEXT
 , "uuid" TEXT
 , "customer" TEXT
 , "customer_address_1" TEXT
 , "customer_address_2" TEXT
 , "customer_vat" NUMERIC(18,2) NULL
 , "customer_email" TEXT
 , "customer_timesheet_email" TIMESTAMP NULL
 , "customer_invoice_email" TEXT
 , "customer_domains" TEXT
 , "updated_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "contracts" (
  id BIGSERIAL PRIMARY KEY
 , "url" TEXT
 , "folder" TEXT
 , "file_name" TEXT
 , "uuid" TEXT
 , "contract_start_date" DATE NULL
 , "contract_end_date" DATE NULL
 , "contract_signed_date" DATE NULL
 , "contract_last_renewal_date" DATE NULL
 , "contract_payment_term" TEXT
 , "contract_currency" TEXT
 , "customer_uuid" TEXT
 , "customer" TEXT
 , "total_invoiced_work" NUMERIC(18,2) NULL
 , "total_invoiced_travel" NUMERIC(18,2) NULL
 , "total_invoiced_net" NUMERIC(18,2) NULL
 , "total_invoiced_vat" NUMERIC(18,2) NULL
 , "total_invoiced_gross" NUMERIC(18,2) NULL
 , "updated_date" TIMESTAMP NULL
 , "created_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "employees" (
  id BIGSERIAL PRIMARY KEY
 , "url" TEXT
 , "folder" TEXT
 , "file_name" TEXT
 , "uuid" TEXT
 , "employee" TEXT
 , "employee_address_1" TEXT
 , "employee_address_2" TEXT
 , "employee_email" TEXT
 , "salary" NUMERIC(18,2) NULL
 , "ccss_contribution" TEXT
 , "ccss_monthly" TEXT
 , "ccss_in_period" TEXT
 , "signed_date" DATE NULL
 , "end_date" DATE NULL
 , "updated_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "employee_contracts" (
  id BIGSERIAL PRIMARY KEY
 , "uuid" TEXT
 , "contract_uuid" TEXT
 , "contract_start_date" DATE NULL
 , "contract_end_date" DATE NULL
 , "customer_uuid" TEXT
 , "customer" TEXT
 , "employee_uuid" TEXT
 , "employee_name" TEXT
 , "employee_signed_date" DATE NULL
 , "employee_end_date" DATE NULL
 , "employee_contracts_start_date" DATE NULL
 , "employee_contracts_end_date" DATE NULL
 , "contract_work_day_price" NUMERIC(18,2) NULL
 , "contract_travel_day_price" NUMERIC(18,2) NULL
 , "updated_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "timesheets" (
  id BIGSERIAL PRIMARY KEY
 , "url" TEXT
 , "folder" TEXT
 , "uuid" TEXT
 , "file_name" TEXT
 , "timesheet_period_start_date" DATE NULL
 , "customer_employee_contract_uuid" TEXT
 , "bank_uuid" TEXT
 , "bank" TEXT
 , "timesheet_date" DATE NULL
 , "timesheet_hours_worked" TIMESTAMP NULL
 , "timesheet_days_travel" TIMESTAMP NULL
 , "timesheet_approval_date" DATE NULL
 , "timesheet_approval_from" TIMESTAMP NULL
 , "timesheet_approval_message_id" TIMESTAMP NULL
 , "customer_uuid" TEXT
 , "contract_uuid" TEXT
 , "customer" TEXT
 , "customer_address_1" TEXT
 , "customer_address_2" TEXT
 , "customer_vat" NUMERIC(18,2) NULL
 , "customer_email" TEXT
 , "customer_timesheet_email" TIMESTAMP NULL
 , "customer_invoice_email" TEXT
 , "customer_domains" TEXT
 , "signed_date" DATE NULL
 , "last_renewal_date" DATE NULL
 , "timesheet_payment_term" TEXT
 , "timesheet_currency" TIMESTAMP NULL
 , "timesheet_work_day_price" TIMESTAMP NULL
 , "timesheet_travel_day_price" TIMESTAMP NULL
 , "timesheet_employee_uuid" TIMESTAMP NULL
 , "timesheet_employee" TIMESTAMP NULL
 , "timesheet_period_total_days" TIMESTAMP NULL
 , "timesheet_period_bank_holidays" TIMESTAMP NULL
 , "timesheet_period_work_days" TIMESTAMP NULL
 , "timesheet_days_worked" TIMESTAMP NULL
 , "timesheet_holidays" TIMESTAMP NULL
 , "timesheet_amount_work" TIMESTAMP NULL
 , "timesheet_amount_travel" TIMESTAMP NULL
 , "timesheet_amount_total" TIMESTAMP NULL
 , "timesheet_doc_num_calc" TIMESTAMP NULL
 , "timesheet_invoice_out_number" TIMESTAMP NULL
 , "timesheet_amount_work_invoice" TIMESTAMP NULL
 , "timesheet_amount_travel_invoice" TIMESTAMP NULL
 , "timesheet_invoice_out_eur_net" TIMESTAMP NULL
 , "timesheet_invoice_out_eur_vat" TIMESTAMP NULL
 , "timesheet_amount_total_invoice" TIMESTAMP NULL
 , "updated_date" TIMESTAMP NULL
 , "created_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "invoices_out" (
  id BIGSERIAL PRIMARY KEY
 , "folder" TEXT
 , "uuid" TEXT
 , "file_name" TEXT
 , "url" TEXT
 , "invoice_out_number" TEXT
 , "invoice_out_date" DATE NULL
 , "invoice_out_due_date" DATE NULL
 , "invoice_out_customer" TEXT
 , "invoice_out_period_start_date" DATE NULL
 , "invoice_out_days_worked" NUMERIC(18,2) NULL
 , "invoice_out_days_travel" NUMERIC(18,2) NULL
 , "invoice_out_eur_work_amount" NUMERIC(18,2) NULL
 , "invoice_out_eur_travel_amount" NUMERIC(18,2) NULL
 , "invoice_out_eur_net_amount" NUMERIC(18,2) NULL
 , "invoice_out_eur_vat" NUMERIC(18,2) NULL
 , "invoice_out_eur_total_amount" NUMERIC(18,2) NULL
 , "contract_uuid" TEXT
 , "invoice_out_reconciliation_date" DATE NULL
 , "invoice_out_accrual" TEXT
 , "bank" TEXT
 , "iban" TEXT
 , "invoice_out_concat" TEXT
 , "updated_date" TIMESTAMP NULL
 , "created_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "pdf_statements" (
  id BIGSERIAL PRIMARY KEY
 , "url" TEXT
 , "folder" TEXT
 , "uuid" TEXT
 , "file_name" TEXT
 , "bank_uuid" TEXT
 , "bank" TEXT
 , "iban" TEXT
 , "pdf_statement_period" TEXT
 , "pdf_statement_total_debit" NUMERIC(18,2) NULL
 , "pdf_statement_total_credit" NUMERIC(18,2) NULL
 , "pdf_statement_new_balance" NUMERIC(18,2) NULL
 , "transaction_date_ref_balance" DATE NULL
 , "updated_date" TIMESTAMP NULL
 , "created_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "budget" (
  id BIGSERIAL PRIMARY KEY
 , "name" TEXT
 , "y2024" TEXT
 , "y2025" TEXT
 , "y2026" TEXT
 , "_spacer_1" TEXT
 , "ccss" TEXT
 , "ccss_row_name" TEXT
 , "val1" TEXT
 , "val2" TEXT
 , "val3" TEXT
 , "val4" TEXT
 , "val5" TEXT
 , "val6" TEXT
 , "val7" TEXT
 , "val8" TEXT
);

CREATE TABLE IF NOT EXISTS "properties" (
  id BIGSERIAL PRIMARY KEY
 , "key" TEXT
 , "value" TEXT
 , "updated_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "banks" (
  id BIGSERIAL PRIMARY KEY
 , "url" TEXT
 , "folder" TEXT
 , "file_name" TEXT
 , "uuid" TEXT
 , "bank" TEXT
 , "iban" TEXT
 , "accounting" TEXT
 , "address" TEXT
 , "bic" TEXT
 , "beneficiary" TEXT
 , "created_date" TIMESTAMP NULL
 , "updated_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "reimbursements" (
  id BIGSERIAL PRIMARY KEY
 , "uuid" TEXT
 , "reimbursement_invoice_in_concat" TEXT
 , "reimbursement_invoice_in_uuid" TEXT
 , "reimbursement_invoice_in_url" TEXT
 , "reimbursement_invoice_in_number" TEXT
 , "reimbursement_invoice_in_date" DATE NULL
 , "reimbursement_invoice_in_category" TEXT
 , "reimbursement_invoice_in_merchant" TEXT
 , "reimbursement_invoice_in_amout_eur" TEXT
 , "reimbursement_invoice_in_transactions" TEXT
 , "reimbursement_invoice_in_reimbursement_amount" NUMERIC(18,2) NULL
 , "reimbursements_invoice_in_reimbursed" TEXT
 , "reimbursement_transaction_concat" TEXT
 , "reimbursement_transaction_date" DATE NULL
 , "reimbursement_transaction_description" TEXT
 , "reimbursement_transaction_debit_amount" NUMERIC(18,2) NULL
 , "reimbursement_transaction_uuid" TEXT
 , "updated_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "transactions" (
  id BIGSERIAL PRIMARY KEY
 , "transaction_date" DATE NULL
 , "transaction_description" TEXT
 , "transaction_debit_amount" NUMERIC(18,2) NULL
 , "transaction_credit_amount" NUMERIC(18,2) NULL
 , "transaction_currency" TEXT
 , "transaction_acc_balance" NUMERIC(18,2) NULL
 , "bank_uuid" TEXT
 , "bank" TEXT
 , "iban" TEXT
 , "transaction_country" TEXT
 , "transaction_category" TEXT
 , "transaction_merchant" TEXT
 , "transaction_doc_number" TEXT
 , "transaction_receipt_line" TEXT
 , "transaction_invoice" TEXT
 , "invoice_uuid" TEXT
 , "uuid" TEXT
 , "url" TEXT
 , "folder" TEXT
 , "file_name" TEXT
 , "updated_date" TIMESTAMP NULL
 , "transaction_date_in_invoice_position" TIMESTAMP NULL
 , "transaction_concat" TEXT
 , "created_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "invoices_in" (
  id BIGSERIAL PRIMARY KEY
 , "uuid" TEXT
 , "file_name" TEXT
 , "folder" TEXT
 , "url" TEXT
 , "invoice_in_number" TEXT
 , "invoice_in_date" DATE NULL
 , "invoice_in_merchant_country" TEXT
 , "invoice_in_category" TEXT
 , "invoice_in_merchant" TEXT
 , "invoice_in_due_date" DATE NULL
 , "invoice_in_period_start_date" DATE NULL
 , "invoice_in_period_end_date" DATE NULL
 , "invoice_in_currency" TEXT
 , "invoice_in_original_amount" NUMERIC(18,2) NULL
 , "invoice_in_original_vat" NUMERIC(18,2) NULL
 , "invoice_in_eur_amount" NUMERIC(18,2) NULL
 , "invoice_in_eur_vat" NUMERIC(18,2) NULL
 , "invoice_in_eur_net" NUMERIC(18,2) NULL
 , "invoice_in_reimbursable" TEXT
 , "invoice_in_transaction_date" DATE NULL
 , "invoice_in_transaction_description" TEXT
 , "invoice_in_transactions" TEXT
 , "updated_date" TIMESTAMP NULL
 , "invoice_in_concat" TEXT
 , "invoice_in_reimbursed" TEXT
 , "reimbursement_transaction" TEXT
 , "created_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "holidays" (
  id BIGSERIAL PRIMARY KEY
 , "uuid" TEXT
 , "public_holiday" TEXT
 , "date" DATE NULL
 , "weekday" TEXT
 , "updated_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "amortizations" (
  id BIGSERIAL PRIMARY KEY
 , "url" TEXT
 , "folder" TEXT
 , "file_name" TEXT
 , "uuid" TEXT
 , "purchase_date" DATE NULL
 , "description" TEXT
 , "asset_account" TEXT
 , "amount_net" NUMERIC(18,2) NULL
 , "amort_per_period" TEXT
 , "num_periods" TEXT
 , "months_1st_period" TEXT
 , "y2023" TEXT
 , "y2024" TEXT
 , "y2025" TEXT
 , "y2026" TEXT
 , "y2027" TEXT
 , "y2028" TEXT
 , "y2029" TEXT
 , "y2030" TEXT
 , "y2031" TEXT
 , "y2032" TEXT
 , "y2033" TEXT
 , "y2034" TEXT
 , "total" NUMERIC(18,2) NULL
 , "updated_date" TIMESTAMP NULL
 , "created_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "supplier_contracts" (
  id BIGSERIAL PRIMARY KEY
 , "uuid" TEXT
 , "file_name" TEXT
 , "folder" TEXT
 , "url" TEXT
 , "supplier_name" TEXT
 , "supplier_category" TEXT
 , "supplier_contract_start_date" DATE NULL
 , "supplier_contract_end_date" DATE NULL
 , "supplier_contract_payment_frequency" TEXT
 , "supplier_contract_duration_in_frequency_units" TEXT
 , "supplier_contract_gross_budget" NUMERIC(18,2) NULL
 , "supplier_contract_tax_net_budget" NUMERIC(18,2) NULL
 , "supplier_contract_gross_budget_by_frequency" NUMERIC(18,2) NULL
 , "supplier_contract_tax_net_budget_by_frequency" NUMERIC(18,2) NULL
 , "supplier_contract_gross_budget_by_year" NUMERIC(18,2) NULL
 , "supplier_contract_tax_net_budget_by_year" NUMERIC(18,2) NULL
 , "updated_date" TIMESTAMP NULL
 , "created_date" TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS "ledger" (
  id BIGSERIAL PRIMARY KEY
 , "date" DATE NULL
 , "account" TEXT
 , "description" TEXT
 , "memo" TEXT
 , "ref" TEXT
 , "debit" NUMERIC(18,2) NULL
 , "credit" NUMERIC(18,2) NULL
);

CREATE TABLE IF NOT EXISTS "balances" (
  id BIGSERIAL PRIMARY KEY
 , "year" TEXT
 , "account" TEXT
 , "name" TEXT
 , "total_debit" NUMERIC(18,2) NULL
 , "total_credit" NUMERIC(18,2) NULL
 , "net_balance" NUMERIC(18,2) NULL
);

CREATE INDEX IF NOT EXISTS idx_contracts_customer_uuid ON "contracts"("customer_uuid");

CREATE INDEX IF NOT EXISTS idx_employee_contracts_contract_uuid ON "employee_contracts"("contract_uuid");

CREATE INDEX IF NOT EXISTS idx_employee_contracts_customer_uuid ON "employee_contracts"("customer_uuid");

CREATE INDEX IF NOT EXISTS idx_employee_contracts_employee_uuid ON "employee_contracts"("employee_uuid");

CREATE INDEX IF NOT EXISTS idx_timesheets_customer_employee_contract_uuid ON "timesheets"("customer_employee_contract_uuid");

CREATE INDEX IF NOT EXISTS idx_timesheets_bank_uuid ON "timesheets"("bank_uuid");

CREATE INDEX IF NOT EXISTS idx_timesheets_customer_uuid ON "timesheets"("customer_uuid");

CREATE INDEX IF NOT EXISTS idx_timesheets_contract_uuid ON "timesheets"("contract_uuid");

CREATE INDEX IF NOT EXISTS idx_timesheets_timesheet_employee_uuid ON "timesheets"("timesheet_employee_uuid");

CREATE INDEX IF NOT EXISTS idx_pdf_statements_bank_uuid ON "pdf_statements"("bank_uuid");

CREATE INDEX IF NOT EXISTS idx_transactions_bank_uuid ON "transactions"("bank_uuid");

CREATE INDEX IF NOT EXISTS idx_transactions_invoice_uuid ON "transactions"("invoice_uuid");

CREATE INDEX IF NOT EXISTS idx_invoices_out_contract_uuid ON "invoices_out"("contract_uuid");