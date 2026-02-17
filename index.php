<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management System</title>

    <style>
        * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background-color: #f4f6f9;
}


header {
    background-color: #2c3e50;
    color: white;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

header nav a {
    color: white;
    margin-left: 20px;
    text-decoration: none;
}

header nav a:hover {
    text-decoration: underline;
}


.dashboard {
    display: flex;
    justify-content: space-around;
    margin: 30px;
}

.card {
    background-color: white;
    padding: 20px;
    width: 25%;
    text-align: center;
    border-radius: 8px;
    box-shadow: 0px 4px 8px rgba(0,0,0,0.1);
}

.card h2 {
    margin-bottom: 10px;
    color: #2c3e50;
}


.form-section {
    margin: 30px;
    background: white;
    padding: 20px;
    border-radius: 8px;
}

.form-section form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.form-section input {
    flex: 1 1 30%;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.form-section button {
    padding: 10px 20px;
    background-color: #27ae60;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.form-section button:hover {
    background-color: #219150;
}


.table-section {
    margin: 30px;
    background: white;
    padding: 20px;
    border-radius: 8px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th, table td {
    padding: 12px;
    border: 1px solid #ddd;
    text-align: center;
}

table th {
    background-color: #2c3e50;
    color: white;
}

table tr:hover {
    background-color: #f1f1f1;
}

    </style>
</head>
<body>
    <header>
        <h1>Pharmacy Management System</h1>
        <nav>
            <a href="#">Dashboard</a>
            <a href="#">Add Medicine</a>
            <a href="#">Stock</a>
            <a href="#">Sales</a>
            <a href="#">Logout</a>
        </nav>
    </header>

    
    <section class="dashboard">
        <div class="card">
            <h2>Total Medicines</h2>
            <p>120</p>
        </div>
        <div class="card">
            <h2>Low Stock</h2>
            <p>8</p>
        </div>
        <div class="card">
            <h2>Today's Sales</h2>
            <p>৳ 15,000</p>
        </div>
    </section>

   <!-- Medicine add -->
    <section class="form-section">
        <h2>Add New Medicine</h2>
        <form>
            <input type="text" placeholder="Medicine Name" required>
            <input type="text" placeholder="Category" required>
            <input type="number" placeholder="Price" required>
            <input type="number" placeholder="Quantity" required>
            <input type="date" required>
            <button type="submit">Add Medicine</button>
        </form>
    </section>

    <!-- Medicine table -->
    <section class="table-section">
        <h2>Medicine List</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Expiry Date</th>
                </tr>
            </thead>
            
        </table>
    </section>

</body>
</html>