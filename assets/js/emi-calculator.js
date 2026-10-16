
// Listen for submit
document.getElementById('loan-form').addEventListener('submit', calculateResults);

// Calculate Results
function calculateResults(e){

// console.log("calculating");

// Declare UI Variables

const amount = document.getElementById('amount');
const interest = document.getElementById('interest');
const years = document.getElementById('years');
const monthlyPayment = document.getElementById('monthly-payment');
const totalPayment = document.getElementById('total-payment');
const totalInterest = document.getElementById('total-interest');

// Turn amount into decimal and store it into variable
const principal = parseFloat(amount.value);
const calculatedInterest = parseFloat(interest.value) /100 / 12;
const calculatedPayment =  parseFloat(years.value) * 12;

// Compute monthly payments
const x = Math.pow(1 + calculatedInterest, calculatedPayment);
const monthly = (principal * x * calculatedInterest)/(x-1);


// Check if value is finite

if(isFinite(monthly)){
	monthlyPayment.value = monthly.toFixed(2);
	totalPayment.value = (monthly * calculatedPayment).toFixed(2);
	totalInterest.value = ((monthly * calculatedPayment) - principal).toFixed(2);

} else{
	showError("Please check your numbers")
}

e.preventDefault();
}

// Function to show error
function showError(error){
	// create div
	const errorDiv = document.createElement('div');

	// Get card and heading in order to add new div to DOM. Parent element
	const card = document.querySelector('.card');
	const heading = document.querySelector('.heading');


	// Give div a class name
	errorDiv.className = 'alert alert-danger';

	// Create text and append div
	errorDiv.appendChild(document.createTextNode(error));

	// Insert error above heading. Insert above will take in the parent element which is the card in this case and for the parameters
	// It will take in the element you want to put in and the element you want to put it before, in this case errorDiv and the heading

	card.insertBefore(errorDiv, heading);

	// Clear error after 3 seconds

	setTimeout(clearError, 3000);

}


// Create clear error
function clearError(){
	document.querySelector('.alert').remove();
}