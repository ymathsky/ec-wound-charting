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
                schema[current_table] = {}
                continue
            
            # Detect End of Table
            if line.startswith(') ENGINE='):
                current_table = None
                continue
                
            # Detect Columns
            if current_table and line and not line.startswith('--') and not line.startswith('/*') and not line.startswith('PRIMARY KEY') and not line.startswith('KEY') and not line.startswith('CONSTRAINT') and not line.startswith('UNIQUE KEY') and not line.startswith(')'):
                # Extract column name
                col_match = re.search(r'`(\w+)`', line)
                if col_match:
                    col_name = col_match.group(1)
                    # Normalize line: remove trailing comma
                    clean_line = line.rstrip(',')
                    schema[current_table][col_name] = clean_line
                    
    return schema

def compare_schemas(local_schema, hosting_schema):
    # 1. Tables in Local but not in Hosting
    missing_tables_in_hosting = [t for t in local_schema if t not in hosting_schema]
    
    # 2. Tables in Hosting but not in Local
    missing_tables_in_local = [t for t in hosting_schema if t not in local_schema]
    
    # 3. Column Differences
    col_diffs = {}
    
    common_tables = [t for t in local_schema if t in hosting_schema]
    
    for table in common_tables:
        local_cols = local_schema[table]
        hosting_cols = hosting_schema[table]
        
        # Cols in Local but not Hosting
        missing_cols_in_hosting = [c for c in local_cols if c not in hosting_cols]
        
        # Cols in Hosting but not Local
        missing_cols_in_local = [c for c in hosting_cols if c not in local_cols]
        
        # Cols with different definitions
        diff_defs = []
        common_cols = [c for c in local_cols if c in hosting_cols]
        for col in common_cols:
            if local_cols[col] != hosting_cols[col]:
                diff_defs.append({
                    'col': col,
                    'local': local_cols[col],
                    'hosting': hosting_cols[col]
                })
                
        if missing_cols_in_hosting or missing_cols_in_local or diff_defs:
            col_diffs[table] = {
                'missing_in_hosting': missing_cols_in_hosting,
                'missing_in_local': missing_cols_in_local,
                'diff_defs': diff_defs
            }
            
    return missing_tables_in_hosting, missing_tables_in_local, col_diffs

local_file = 'local_schema.sql'
hosting_file = 'hosting_schema.sql'

local_schema = parse_sql_schema(local_file)
hosting_schema = parse_sql_schema(hosting_file)

missing_in_hosting, missing_in_local, col_diffs = compare_schemas(local_schema, hosting_schema)

print("=== COMPARISON REPORT ===")
print(f"Local File: {local_file}")
print(f"Hosting File: {hosting_file}")

if missing_in_hosting:
    print("\n[!] Tables present LOCALLY but missing on HOSTING:")
    for t in missing_in_hosting: print(f"  - {t}")

if missing_in_local:
    print("\n[!] Tables present on HOSTING but missing LOCALLY:")
    for t in missing_in_local: print(f"  - {t}")

if col_diffs:
    print("\n[!] Column Differences:")
    for table, diffs in col_diffs.items():
        print(f"\n  Table: {table}")
        if diffs['missing_in_hosting']:
            print("    Missing in Hosting:")
            for c in diffs['missing_in_hosting']: print(f"      - {c}")
        if diffs['missing_in_local']:
            print("    Missing in Local:")
            for c in diffs['missing_in_local']: print(f"      - {c}")
        if diffs['diff_defs']:
            print("    Definition Mismatch:")
            for d in diffs['diff_defs']:
                print(f"      Column: {d['col']}")
                print(f"        Local:   {d['local']}")
                print(f"        Hosting: {d['hosting']}")
