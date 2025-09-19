## Rewards Store

### Tables

#### `rewards`

* **Purpose**: Store catalog of redeemable items.
* **Fields**:

  * `id`
  * `name`, `description`, `image_url`
  * `gold_cost`
  * `stock`
  * `is_active`

#### `reward_redemptions`

* **Purpose**: Track student redemption requests & fulfillment.
* **Fields**:

  * `id`
  * `student_id` (FK → students)
  * `reward_id` (FK → rewards)
  * `quantity`
  * `gold_spent`
  * `status` (ENUM: pending, confirmed, shipped, delivered, cancelled)
  * `requested_at`
  * `processed_by`

### Goals & Features

* Students can redeem Gold for items.
* Workflow from pending → delivered.
* Gold deducted on request creation.
* API: `POST /rewards/{id}/redeem`, `PUT /reward-redemptions/{id}/status`.

