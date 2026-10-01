# General stores and procurement

**Menus:** Stores & Procurement → General Store, Requisitions, Purchase Orders, Supplier Invoices

| Who | Typical tasks |
|---|---|
| Ward / department staff | Request items with a requisition |
| Storekeeper | Issue requisitions, receive deliveries, adjust stock, raise purchase orders |
| Pharmacist | Order drugs on purchase orders |
| Accountant | Approve purchase orders, record and pay supplier invoices |

## General store

The **General Store** holds non-drug items: medical consumables, laboratory reagents, linen, stationery, cleaning, kitchen and maintenance items. It shows stock on hand, reorder level, average cost and value, with filters for low and out-of-stock items. Select an item to see its full **stock ledger** (every receipt, issue and adjustment, with department and person).

- **Store items** are added under General Store → **Items** (code, name, category, unit of issue, reorder level).
- **Receive** stock that arrives without a purchase order — donations, petty-cash purchases or opening balances — with an optional unit cost. Stock is valued at weighted average cost.
- **Adjust stock** on the item page: stock-count correction (+/−), write-off (damaged, expired, lost) or return from a department, always with a reason.

## Requisitions

1. Ward staff select **Requisitions → New requisition**, choose the department or ward (defaults to theirs), the date needed and the items with quantities, and **Send to store**.
2. The storekeeper opens it from **To issue**, sees stock in the store and enters the quantity to **Issue** for each item (part issues are allowed).
3. If items cannot be supplied, the storekeeper closes the requisition with a reason the department sees.

Staff see their own and their department's requisitions and their status (Waiting for store, Partly issued, Issued, Rejected, Cancelled).

## Purchase orders

1. **New order:** choose the supplier, dates and lines — store items and/or drugs — with quantity and unit price. **Reorder** pre-fills all items at or below reorder level. The order is saved as a **draft** (PO…).
2. **Approve:** someone with approval permission (not the person who raised it) approves it. Then **Print PO** and send it to the supplier.
3. **Receive delivery:** enter the quantities delivered (part deliveries allowed) and the delivery note number. Drugs need a batch number and expiry date and go straight into pharmacy stock; store items go into the general store at the order price.
4. **Close short** an order that will never be delivered in full; **Cancel** a draft or approved order with a reason.

## Supplier invoices

Record each supplier invoice (supplier, purchase order, invoice number and date, due date, amount). If the invoiced amount is more than the value of goods received on the order, a warning is shown — check before paying. To pay, enter the payment date and transfer/cheque reference and select **Paid**; this must be done by someone other than the person who entered the invoice. Tabs show Unpaid, Overdue and Paid invoices.

## Reports

**Store consumption by department** (value of items issued, biggest items, stock value) and **Purchases & supplier payables** (goods received per supplier, paid, owed and overdue).
