exports.handler = async (event, context) => {
  // Só processa requisições POST
  if (event.httpMethod !== 'POST') {
    return {
      statusCode: 405,
      body: JSON.stringify({ error: 'Method Not Allowed' }),
    }
  }

  try {
    const path = event.path.replace('/.netlify/functions/proxy/', '')
    const url = `https://acampamento-desbravadores.infinityfree.me/api/${path}`

    console.log('Proxy: ', event.path, '→', url)

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: event.body,
    })

    const data = await response.text()

    return {
      statusCode: 200,
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Headers': 'Content-Type',
        'Access-Control-Allow-Methods': 'POST, OPTIONS',
      },
      body: data,
    }
  } catch (error) {
    return {
      statusCode: 500,
      body: JSON.stringify({ error: 'Proxy error: ' + error.message }),
    }
  }
}
