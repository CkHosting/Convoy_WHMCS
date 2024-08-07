# Convoy WHMCS Module

This WHMCS module integrates with Convoy, allowing for seamless provisioning, management, and automation of virtual machines directly from WHMCS.

## Key Features

- **Automated Provisioning**: Automatically create, suspend, unsuspend, and terminate virtual machines.
- **User Creaton**: Create and user accounts associated with virtual machines.
- **Error Logging**: Detailed error logging for easy troubleshooting.
- **Addons and Upgrades/Downgrades**: Support for product addons and upgrades or downgrades of existing services.

## Installation

1. **Download the Module**: Download the Convoy WHMCS module from the [repository](#).
2. **Upload Files**: Upload the module files to the `/modules/servers/` directory of your WHMCS installation.
3. **Activate the Module**:
   - Log in to your WHMCS admin panel.
   - Navigate to `Setup` > `Products/Services` > `Servers`.
   - Click on `Add New Server`.
   - Click on `Go To Advanced Mode`.

4. **Configure Server Settings**:
   - **Name**: Enter a name for the server.
   - **Hostname**: Enter the hostname of your Convoy Panel (e.g., `192.0.0.1` or `example.convoy.panel`).
   - **IP Address**: Enter the IP address of your Convoy Panel (e.g., `192.0.0.1`).
   - **Module**: Select `Convoy` from the dropdown menu.

5. **Obtain API Token**:
   - Log in to your Convoy Panel and navigate to the Admin Control Panel.
   - Find the `Tokens` section and click on `New Token`.
   - Enter a name for the token and click `Create`.
   - Copy the generated token.

6. **Complete WHMCS Configuration**:
   - Return to your WHMCS admin panel.
   - Paste the copied token into the `Password` field.
   - If using SSL, check the `Secure` checkbox.
   - Click `Save` to finalize the server configuration.

7. **Create a Product**:
   - Navigate to `Setup` > `Products/Services` > `Products/Services`.
   - Create a new product and select the `Convoy` module in the `Module Settings` tab.
   - Configure the product settings as required, including resource allocations and other options.


## Configuration Options

The module supports the following configuration options:

- `node_id`: The ID of the node (optional/required).
- `location_id`: The ID of the location (optional/required).
- `name`: The name of the virtual machine (optional).
- `hostname`: The hostname of the virtual machine (optional).
- `cpu`: Number of CPUs (required).
- `memory`: Amount of memory in MB (required).
- `disk`: Disk space in MB (required).
- `snapshots`: Number of snapshots (optional).
- `backups`: Number of backups (optional).
- `bandwidth`: Bandwidth limit (optional).
- `account_password`: Account password (optional).
- `template_uuid`: Template UUID (required).
- `ipv4`: Number of IPv4 addresses (optional, default: 0).
- `ipv6`: Number of IPv6 addresses (optional, default: 0).

## Usage

Once installed and configured, the module will automatically handle provisioning, suspension, unsuspension, and termination of virtual machines based on the actions performed within WHMCS.

### Addons and Upgrades/Downgrades

The module supports adding additional features and resources through addons. It also allows for seamless upgrades or downgrades of existing virtual machines, ensuring that your clients can adjust their services to match their needs.

### Error Handling

The module includes detailed error logging to assist with troubleshooting. Log entries can be found in the module logs within WHMCS.

## Support

For support and further assistance, reach out to support@ck-hosting.com
