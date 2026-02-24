import re

def parse_sql_schema(file_path):
    schema = {}
    current_table = None
    
    with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
        for line in f:
            line = line.strip()
            
            # Detect CREATE TABLE
            create_match = re.search(r'CREATE TABLE `?(\w+)`?', line, re.IGNORECASE)
            if create_match:
                current_table = create_match.group(1)
                schema[current_table] = []
                continue
            
            # Detect End of Table
            if line.startswith(') ENGINE='):
                current_table = None
                continue
                
            # Detect Columns (simplified)
            if current_table and line and not line.startswith('--') and not line.startswith('/*') and not line.startswith('PRIMARY KEY') and not line.startswith('KEY') and not line.startswith('CONSTRAINT') and not line.startswith('UNIQUE KEY'):
                # Extract column name
                col_match = re.search(r'`(\w+)`', line)
                if col_match:
                    col_name = col_match.group(1)
                    schema[current_table].append(line) # Store full line to get type
                    
    return schema

def compare_schemas(old_schema, new_schema):
    missing_tables = []
    missing_columns = {}
    
    # Parse old schema to just names for easy lookup
    old_schema_names = {}
    for t, lines in old_schema.items():
        old_schema_names[t] = set()
        for line in lines:
            m = re.search(r'`(\w+)`', line)
            if m: old_schema_names[t].add(m.group(1))

    for table in new_schema:
        if table not in old_schema:
            missing_tables.append(table)
        else:
            # Check columns
            for line in new_schema[table]:
                m = re.search(r'`(\w+)`', line)
                if m:
                    col_name = m.group(1)
                    if col_name not in old_schema_names[table]:
                        if table not in missing_columns: missing_columns[table] = []
                        missing_columns[table].append(line)
                
    return missing_tables, missing_columns

old_file = 'compare_old.sql' # ecwound1_ecwound (8).sql
new_file = 'compare_new.sql' # ec_wound (32).sql

old_schema = parse_sql_schema(old_file)
new_schema = parse_sql_schema(new_file)

missing_tables, missing_columns = compare_schemas(old_schema, new_schema)

print("--- MISSING TABLES (Present in New, Missing in Old) ---")
for t in missing_tables:
    print(f"- {t}")

print("\n--- MISSING COLUMNS (Present in New, Missing in Old) ---")
for table, cols in missing_columns.items():
    for col in cols:
        print(f"Table '{table}': {col}")
