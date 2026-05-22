<?php // includes/customer_form_fields.php — reusable add-customer fields ?>
<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Full Name <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control" placeholder="Juan Dela Cruz" required />
  </div>
  <div class="col-md-6">
    <label class="form-label">Email Address</label>
    <input type="email" name="email" class="form-control" placeholder="juan@example.com" />
  </div>
  <div class="col-md-6">
    <label class="form-label">Phone Number</label>
    <input type="text" name="phone" class="form-control" placeholder="09XX-XXX-XXXX" />
  </div>
  <div class="col-md-6">
    <label class="form-label">Address</label>
    <input type="text" name="address" class="form-control" placeholder="City, Province" />
  </div>
  <div class="col-12">
    <label class="form-label">Notes</label>
    <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes…"></textarea>
  </div>
</div>
