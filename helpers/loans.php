<?php
function createRepaymentSchedule($pdo, $loan_id) {
    // Fetch loan details
    $stmt = $pdo->prepare("SELECT amount, interest_rate, term_months FROM loans WHERE id = :loan_id");
    $stmt->execute([':loan_id' => $loan_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        throw new Exception("Loan not found");
    }
    
    $principal = $loan['amount'];
    $interest_rate = $loan['interest_rate'] / 100;
    $term = $loan['term_months'];
    
    // Calculate monthly payment (simple interest)
    $total_interest = $principal * $interest_rate * ($term / 12);
    $total_payment = $principal + $total_interest;
    $monthly_payment = $total_payment / $term;
    
    // Create repayment schedule
    $due_date = date('Y-m-d', strtotime('+1 month'));
    for ($i = 1; $i <= $term; $i++) {
        $stmt = $pdo->prepare("INSERT INTO loan_repayments 
                              (loan_id, due_date, amount, status) 
                              VALUES (:loan_id, :due_date, :amount, 'pending')");
        $stmt->execute([
            ':loan_id' => $loan_id,
            ':due_date' => $due_date,
            ':amount' => $monthly_payment
        ]);
        $due_date = date('Y-m-d', strtotime($due_date . ' +1 month'));
    }
}