## Student Wallets

### Tables

#### `student_wallets`

* **Purpose**: Track current Gold balance of each student.
* **Fields**:

  * `id`
  * `student_id` (FK → students)
  * `balance` (DECIMAL)
  * `updated_at`

#### `wallet_transactions`

* **Purpose**: Log every Gold change for transparency & auditing.
* **Fields**:

  * `id`
  * `student_id` (FK → students)
  * `amount` (+/-)
  * `type` (ENUM: earn, spend, adjust)
  * `source_type` (ENUM: event, reward, manual)
  * `source_id` (nullable FK)
  * `notes`
  * `created_at`

### Goals & Features

* Maintain Gold balance.
* View transaction history.
* Support manual adjustment by staff.
