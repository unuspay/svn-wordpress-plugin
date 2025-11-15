=== UnusPay Crypto Payments For Paid Memberships Pro ===
Contributors: UnusPay
Tags: web3, payments, Paid Memberships Pro, cryptocurrency
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.0.0
Requires Plugins: paid-memberships-pro
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept Web3 payments, supporting various cryptocurrency tokens, blockchains and wallets, with the UnusPay Payments extension for Paid-memberships-pro.

== Source Code ==

The plugin includes minified assets in the /assets/js folder.  
Source files can be found at:  
[widgets](https://github.com/unuspay/widgets)
To build the final production bundle, run:
git submodule update --init --recursive
npm install
npm run build --ws 
npm run build:bundle

== Public REST API Endpoints ==

This plugin registers several public REST API endpoints under the `/wp-json/unuspay/wc/` namespace. These endpoints are intentionally exposed without authentication (`__return_true`) for integration with the UnusPay payment platform. They are safe for public use and designed for communication between the Paid-memberships-pro store and UnusPay.

- `/wp-json/unuspay/wc/checkouts/{id}`  
  Used by the user to create a UnusPay order and initiate the payment process.

- `/wp-json/unuspay/wc/track`  
  Used by the user to submit payment results to UnusPay for tracking the payment status.

- `/wp-json/unuspay/wc/release`  
  Used by the user to query the payment status from UnusPay to check whether the transaction has been verified.

- `/wp-json/unuspay/wc/validate`  
  Called by UnusPay to send a notification after the transaction has been successfully verified.

These endpoints are required for the payment workflow and are meant to be accessible from external clients and the UnusPay server. If needed, additional security mechanisms such as token validation can be implemented on the server side.

== External Services ==

This plugin relies on external services provided by UnusPay to enable cryptocurrency payments.  
Below are the external endpoints used, along with explanations of what data is sent, when, and why:

1. **UnusPay Blockchain Info API**  
   - **Endpoint**: https://dapp.unuspay.com/api/payment/link/blockchains  
   - **Purpose**: Retrieves a list of supported blockchains for a given payment.  
   - **Data Sent**: payment key
   - **When**: Called when initiating a new crypto payment to show available blockchain options.  
   - **Terms of Service**: https://unuspay.com/terms-of-use/  
   - **Privacy Policy**: https://unuspay.com/privacy-policy/

2. **UnusPay Order Creation API**  
   - **Endpoint**: https://dapp.unuspay.com/api/payment/ecommerce/order  
   - **Purpose**: Creates a crypto payment order on the UnusPay system.  
   - **Data Sent**: website, lang, orderId,email, payment key, currency, amount, commerceType
   - **When**: Called when a user confirms checkout with cryptocurrency as the selected payment method.  
   - **Terms of Service**: https://unuspay.com/terms-of-use/  
   - **Privacy Policy**: https://unuspay.com/privacy-policy/

3. **UnusPay Payment API**  
   - **Endpoint**: https://dapp.unuspay.com/api/payment/pay  
   - **Purpose**: Initiates the payment process for the order.  
   - **Data Sent**: payment informations , etc.Order ID, supported blockchains and tokens, amount  etc. 
   - **When**: Called after order creation when payment is being initiated.  
   - **Terms of Service**: https://unuspay.com/terms-of-use/  
   - **Privacy Policy**: https://unuspay.com/privacy-policy/

4. **UnusPay Payment Status API**  
   - **Endpoint**: https://dapp.unuspay.com/api/payment/release  
   - **Purpose**: Checks the status of a payment to confirm whether it was completed.  
   - **Data Sent**: Order ID.  
   - **When**: Called periodically after payment initiation to track payment confirmation.  
   - **Terms of Service**: https://unuspay.com/terms-of-use/  
   - **Privacy Policy**: https://unuspay.com/privacy-policy/

5. **Web3 Wallet Interaction (e.g., MetaMask, WalletConnect, etc.)**  
    - **Endpoint**: https://verify.walletconnect.com, https://api.mainnet-beta.solana.com, https://usernames.worldcoin.org/api/v1/query etc.
   - **Purpose**: Allows users to authorize and sign blockchain transactions using their own Web3 wallet.  
   - **Data Sent**: Public wallet address, transaction payload (amount, destination address, gas fees), and digital signature — initiated and approved by the user within their wallet app.  
   - **When**: Triggered when a user clicks “Pay with Web3 Wallet” and confirms the transaction using their browser extension or mobile wallet.  
   - **Note**: These actions are handled entirely by the user's wallet and blockchain network. This plugin does not collect or transmit private keys.  
   - **Privacy Policies of common providers**:  
     - MetaMask: https://consensys.net/privacy-policy/  
     - WalletConnect: https://walletconnect.com/privacy-policy/  
     - Ethereum: https://ethereum.org/en/privacy-policy/

6. **UnusPay Token Logo API**  
   - **Endpoint**: https://dapp.unuspay.com/images/${blockchain}/${address}/logo.png  
   - **Purpose**: Retrieves a list of supported blockchains for a given payment.  
   - **Data Sent**: blockchain,address
   - **When**: Called when initiating a new crypto payment to show available token options.  
   - **Note**: This API is included as a feature of our plugin to provide token logos dynamically during the payment process.
      When users initiate a payment and select a token, the plugin fetches the token logo from this API and displays it in the interface.
      Our payment system supports thousands of tokens across multiple blockchains.
         It is not feasible to bundle all logos inside the plugin because:
         The plugin size would become extremely large
         Updating token logos would require frequent plugin releases
         Scalability would be limited as new tokens appear
   - **Terms of Service**: https://unuspay.com/terms-of-use/  
   - **Privacy Policy**: https://unuspay.com/privacy-policy/

### 1. Worldcoin Username API
This service is provided by Worldcoin (world.org) and is used to query usernames associated with wallet addresses, likely for displaying user-friendly identifiers in your plugin's widgets or transaction views.

- Data sent: Wallet addresses (e.g., an array of addresses like [t] in the POST body).
- When: Every time the plugin fetches username data, such as during widget loading or when processing a specific address.
- Terms of Service: https://world.org/legal/user-terms-and-conditions
- Privacy Policy: https://world.org/legal/privacy-notice

### 2. Gnosis Safe Transaction Service API
This service is provided by Gnosis (gnosis.io / safe.global) and is used to retrieve transaction history and details for multisig safes, such as all transactions for a safe or specific multisig transaction data.

- Data sent: Safe addresses, transaction IDs, and related identifiers (e.g., via URL paths like /safes/{address}/all-transactions/ or /multisig-transactions/{tx_id}/).
- When: When the plugin needs to display or process transaction data for a Gnosis Safe, such as in widget updates or on-demand queries.
- Terms of Service: https://www.gnosis.io/legal/terms-conditions
- Privacy Policy: https://www.gnosis.io/legal/privacy-policy

### 3. UnusPay Transaction API
This service is provided by UnusPay (unuspay.com), a decentralized crypto payment platform, and is used to fetch transaction details on the Worldchain network, possibly for verifying or displaying payment information.

- Data sent: Transaction IDs (e.g., via URL paths like /transactions/worldchain/{transaction_id}).
- When: When the plugin queries a specific transaction, such as during widget rendering or user-initiated actions.
- Terms of Service: https://unuspay.com/terms-of-use (assumed based on site navigation; verify and update if the exact link differs).
- Privacy Policy: https://unuspay.com/privacy-policy (assumed based on site navigation; verify and update if the exact link differs).

Note: The UnusPay website mentions "Privacy Policy" and "Terms of Use" in its footer, but exact URLs weren't explicitly listed in public sources. I recommend browsing the site or contacting the service to confirm the links and ensure they point to the correct documents.

### 4. Solana RPC API
This service is provided by Solana (solana.com) and consists of public RPC endpoints for interacting with the Solana blockchain, such as querying blockchain data or submitting transactions.

- Data sent: Blockchain-specific queries (e.g., endpoint URLs like https://api.mainnet-beta.solana.com for mainnet, or others for devnet/testnet/localnet).
- When: Whenever the plugin interacts with the Solana network, such as during chain detection, transaction processing, or widget initialization.
- Terms of Service: https://solana.com/tos
- Privacy Policy: https://solana.com/privacy-policy

### Additional Notes
- The plugin only sends data necessary for the intended functionality (e.g., addresses, IDs) and does not share unrelated user data unless specified otherwise in the code.
- No data is sent without user interaction or widget loading, and users should be aware that blockchain-related data is inherently public.

== Simple Web3 Cryptocurrency Payments with UnusPay ==

[youtube https://www.youtube.com/watch?v=o3ANPF-eXZ0]


== Supported Blockchains ==

* Ethereum
* BNB Smart Chain
* Polygon
* Solana
* Fantom
* Gnosis
* Avalanche
* Arbitrum
* Optimism
* Base

== Supported Tokens ==

All* standard tokens.

* if the token standard is strictly adhered to and the token is convertible on a supported decentralized exchange. Check UnusPay’s documentation for further details about [what tokens are supported](https://unuspay.com/docs/payments/supported/tokens/).

