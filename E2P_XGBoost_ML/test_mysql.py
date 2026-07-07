import mysql.connector

# 1. Edit bahagian ni ikut DB kau
config = {
    "host": "localhost",
    "user": "root",
    "password": "",  # kalau ada password, letak sini
    "database": "entry2profession"  # tukar ikut nama DB kau
}

try:
    # 2. Cuba connect
    conn = mysql.connector.connect(**config)
    print("✅ Berjaya connect ke MySQL!")

    cursor = conn.cursor()

    # 3. Test query simple: list tables
    cursor.execute("SHOW TABLES;")
    tables = cursor.fetchall()

    print("Senarai tables dalam DB:")
    for t in tables:
        print("-", t[0])

    cursor.close()
    conn.close()
    print("✅ Connection ditutup dengan selamat.")

except mysql.connector.Error as err:
    print("❌ Ralat MySQL:", err)
