import React from 'react'
import ReactDOM from 'react-dom'
import { options } from './configuration/graph'
import { Viewer } from './Components/Viewer.jsx'

ReactDOM.render(
    <Viewer
        options={options}
    />
, document.getElementById('viewer-wrapper'))
