import pymysql

try:
    connection = pymysql.connect(
        host='localhost',
        user='linebot_wh',
        password='warehouse_pass_2026',
        database='warehouse',
        charset='utf8mb4'
    )
    with connection.cursor() as cursor:
        print("Resetting data via Python...")
        cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
        cursor.execute("TRUNCATE TABLE stocks")
        cursor.execute("TRUNCATE TABLE products")
        cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
        connection.commit()
    print("Success: Data cleared.")
finally:
    connection.close()
